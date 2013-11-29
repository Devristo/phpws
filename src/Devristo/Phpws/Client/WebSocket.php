<?php

namespace Devristo\Phpws\Client;

use Devristo\Phpws\Exceptions\WebSocketInvalidUrlScheme;
use Devristo\Phpws\Framing\WebSocketFrameInterface;
use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketOpcode;
use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\WebSocketTransport;
use Devristo\Phpws\Protocol\WebSocketTransportFactory;
use Devristo\Phpws\Protocol\WebSocketTransportHybi;
use Devristo\Phpws\Protocol\WebSocketConnection;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;
use Zend\Http\Request;
use Zend\Http\Response;

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
     * @var WebSocketConnection
     */
    protected $stream;
    protected $socket;


    protected $request;
    protected $response;

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

        $this->request = new Request();

        $this->request->setUri($this->requestUri);

        $this->request->getHeaders()->addHeaderLine("Connection", "Upgrade");
        $this->request->getHeaders()->addHeaderLine("Host", "{$this->host}");
        $this->request->getHeaders()->addHeaderLine("Sec-WebSocket-Key", $challenge);
        $this->request->getHeaders()->addHeaderLine("Origin", "{$this->origin}");
        $this->request->getHeaders()->addHeaderLine("Sec-WebSocket-Version", 13);
        $this->request->getHeaders()->addHeaderLine("Upgrade", "websocket");

        $this->stream->write($this->request->toString());
    }

    public function onData($data)
    {
        if ($this->state == self::STATE_HANDSHAKE_SENT) {
            $response = Response::fromString($data);
            $this->_connection = new WebSocketTransportHybi($this->stream);
            $this->_connection->setLogger($this->logger);
            $myself = $this;
            $this->_connection->on("message", function ($message) use ($myself) {
                $myself->emit("message", array("message" => $message));
            });
            $this->state = self::STATE_CONNECTED;
            $this->emit("connected", array("response" => $response));
        }
    }

    public function open()
    {
        $errorNumber = $errorString = null;
        $that = $this;

        $protocol = $this->scheme == 'ws' ? "tcp" : "ssl";

        $this->socket = stream_socket_client("$protocol://{$this->host}:{$this->port}", $errorNumber, $errorString, $this->getTimeOut());
        $stream = new Stream($this->socket, $this->loop);

        $stream->on('data', array($this, 'onData'));

        $stream->on('message', function ($message) use ($that) {
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
        if ($this->isClosing)
            return;

        $this->isClosing = true;
        $this->sendFrame(WebSocketFrame::create(WebSocketOpcode::CloseFrame));

        $this->state = self::STATE_CLOSING;
        $stream = $this->stream;

        $closeTimer = $this->loop->addTimer(5, function () use ($stream) {
            $stream->close();
        });

        $loop = $this->loop;
        $stream->once("close", function () use ($closeTimer, $loop) {
            if ($closeTimer)
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
