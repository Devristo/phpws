<?php

namespace Devristo\Phpws\Client;

use Devristo\Phpws\Exceptions\WebSocketInvalidUrlScheme;
use Devristo\Phpws\Framing\WebSocketFrameInterface;
use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketOpcode;
use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\WebSocketTransport;
use Devristo\Phpws\Protocol\WebSocketConnectionFactory;
use Devristo\Phpws\Protocol\WebSocketConnectionHybi;
use Devristo\Phpws\Protocol\WebSocketServerClient;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

class WebSocket extends EventEmitter
{
    const STATE_HANDSHAKE_SENT = 0;
    const STATE_CONNECTED = 1;
    const STATE_CLOSING = 2;
    const STATE_CLOSED = 3;

    protected $state = self::STATE_CLOSED;

    protected $host;
    protected $port;
    protected $requestUri;
    protected $url;
    protected $_timeOut = 1;

    /**
     * @var WebSocketServerClient
     */
    protected $stream;


    /**
     * @var WebSocketTransport
     */
    protected $_connection = null;

    protected $headers;
    protected $loop;

    protected $isClosing = false;

    public function __construct($url, LoopInterface $loop, $logger)
    {
        $this->logger = $logger;
        $this->loop = $loop;
        $parts = parse_url($url);

        $this->url = $url;

        if (in_array($parts['scheme'], array('ws', 'wss')) === false)
            throw new WebSocketInvalidUrlScheme();

        $this->scheme = $parts['scheme'];

        $this->host = $parts['host'];
        $this->port = array_key_exists('port', $parts) ? $parts['port'] : 80;
        $this->path = array_key_exists('path', $parts) ? $parts['path'] : '/';
        $this->query = array_key_exists("query", $parts) ? $parts['query'] : null;

        if (array_key_exists('query', $parts))
            $this->path .= "?" . $parts['query'];

        $this->origin = 'http://' . $this->host;

        if (isset($parts['path']))
            $this->requestUri = $parts['path'];
        else
            $this->requestUri = "/";

        if (isset($parts['query']))
            $this->requestUri .= "?" . $parts['query'];


    }


    private static function randHybiKey()
    {
        return base64_encode(
            chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255))
            . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255))
            . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255))
            . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255)) . chr(rand(0, 255))
        );
    }


    protected function sendHandshake()
    {
        $challenge = self::randHybiKey();

        $this->addHeader("Connection","Upgrade");
        $this->addHeader("Host","{$this->host}");
        $this->addHeader("Sec-WebSocket-Key",$challenge);
        $this->addHeader("Origin","{$this->origin}");
        $this->addHeader("Sec-WebSocket-Version",13);
        $this->addHeader("Upgrade","websocket");

        $strHandshake = "GET {$this->requestUri} HTTP/1.1\r\n";

        foreach ($this->headers as $k => $v) {
            $strHandshake .= $k . " " . $v . "\r\n";
        }

        $strHandshake .= "\r\n";
        $this->stream->write($strHandshake);
    }

    public function onData($data)
    {
        switch ($this->state) {
            case (self::STATE_HANDSHAKE_SENT):
                $headers = WebSocketConnectionFactory::parseHeaders($data);
                $this->_connection = new WebSocketConnectionHybi($this->stream, $headers);
                $myself = $this;
                $this->_connection->on("message", function($message) use ($myself){
                    $myself->emit("message", array("message" => $message));
                });
                $this->state = self::STATE_CONNECTED;
                $this->emit("connected", array("headers" => $headers));
                break;
            case (self::STATE_CONNECTED):
                $this->_connection->onData($data);
        }
    }

    public function open()
    {
        $errno = $errstr = null;
        $that = $this;

        $protocol = $this->scheme == 'ws' ? "tcp" : "ssl";

        $this->socket = stream_socket_client("$protocol://{$this->host}:{$this->port}", $errno, $errstr, $this->getTimeOut());
        $stream = new Stream($this->socket, $this->loop);

        $stream->on('data', array($this, 'onData'));

        $stream->on('message', function($message) use($that){
            $that->emit("message", array("message" => $message));
        });

        $this->stream = $stream;

        $this->sendHandshake();
        $this->state = self::STATE_HANDSHAKE_SENT;

        return $this;
    }

    public function addHeader($key, $value)
    {
        $this->headers[$key . ":"] = $value;
    }

    public function send($string)
    {
        $this->_connection->sendString($string);
    }

    public function sendMessage(WebSocketMessageInterface $msg)
    {
        $this->_connection->sendMessage($msg);
    }

    public function sendFrame(WebSocketFrameInterface $frame)
    {
        $this->_connection->sendFrame($frame);
    }

    public function close()
    {
        if($this->isClosing)
            return;

        $this->isClosing = true;
        $this->sendFrame(WebSocketFrame::create(WebSocketOpcode::CloseFrame));

        $this->state = self::STATE_CLOSING;
        $stream = $this->stream;

        $closeTimer = $this->loop->addTimer(5, function() use ($stream){
            $stream->close();
        });

        $loop = $this->loop;
        $stream->once("close", function() use ($closeTimer, $loop){
            if($closeTimer)
                $loop->cancelTimer($closeTimer);
        });
    }

    public function getTimeOut()
    {
        return $this->_timeOut;
    }

    public function setTimeOut($seconds)
    {
        $this->_timeOut = $seconds;
    }
}
