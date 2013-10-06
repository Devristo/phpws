<?php

namespace Devristo\Phpws\Client;

use Devristo\Phpws\Exceptions\WebSocketInvalidUrlScheme;
use Devristo\Phpws\Framing\IWebSocketFrame;
use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketOpcode;
use Devristo\Phpws\Messaging\IWebSocketMessage;
use Devristo\Phpws\Protocol\IWebSocketConnection;
use Devristo\Phpws\Protocol\WebSocketConnection;
use Devristo\Phpws\Protocol\WebSocketConnectionFactory;
use Devristo\Phpws\Protocol\WebSocketConnectionFlash;
use Devristo\Phpws\Protocol\WebSocketConnectionHixie;
use Devristo\Phpws\Protocol\WebSocketConnectionHybi;
use Devristo\Phpws\Protocol\WebSocketObserver;
use Devristo\Phpws\Protocol\WebSocketStream;

class WebSocket implements WebSocketObserver
{

    protected $socket;
    protected $handshakeChallenge;
    protected $hixieKey1;
    protected $hixieKey2;
    protected $host;
    protected $port;
    protected $origin;
    protected $requestUri;
    protected $url;
    protected $hybi;
    protected $_frames = array();
    protected $_messages = array();
    protected $_head = '';
    protected $_timeOut = 1;

    /**
     * @var WebSocketConnection
     */
    protected $_connection = null;

    protected $headers;

    public function __construct($url, $useHybie = true, $showHeaders = false)
    {
        if (defined('WS_DEBUG_HEADER'))
            define("WS_DEBUG_HEADER", $showHeaders);

        $this->hybi = $useHybie;
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

    /**
     * @return string
     */
    public function getOrigin()
    {
        return $this->origin;
    }


    /**
     * @param string $origin
     */
    public function setOrigin($origin)
    {
        $this->origin = $origin;
    }

    public function onDisconnect(WebSocketStream $s)
    {

    }

    public function onConnectionEstablished(WebSocketStream $s)
    {

    }

    public function onMessage(IWebSocketConnection $s, IWebSocketMessage $msg)
    {
        $this->_messages[] = $msg;
    }

    public function onFlashXMLRequest(WebSocketConnectionFlash $connection)
    {

    }

    /**
     * TODO: Proper header generation!
     * TODO: Check server response!
     */
    public function open()
    {
        $errno = $errstr = null;

        $protocol = $this->scheme == 'ws' ? "tcp" : "ssl";

        $this->socket = stream_socket_client("$protocol://{$this->host}:{$this->port}", $errno, $errstr, $this->getTimeOut());

        // mamta
        if ($this->hybi) {
            $this->buildHeaderArray();
        } else {
            $this->buildHeaderArrayHixie76();
        }
        $buffer = $this->serializeHeaders();

        fwrite($this->socket, $buffer, strlen($buffer));

        // wait for response
        $buffer = fread($this->socket, 8192);
        $headers = WebSocketConnectionFactory::parseHeaders($buffer);

        $s = new WebSocketStream($this, $this->socket, $immediateWrite = true);

        $this->_connection = $this->hybi ? new WebSocketConnectionHybi($s, $headers) : new WebSocketConnectionHixie($s, $headers, $buffer);

        $s->setConnection($this->_connection);

        return true;
    }

    public function getTimeOut()
    {
        return $this->_timeOut;
    }

    public function setTimeOut($seconds)
    {
        $this->_timeOut = $seconds;
    }

    protected function buildHeaderArray()
    {
        $this->handshakeChallenge = WebSocketFunctions::randHybiKey();
        $this->headers = array("GET" => "{$this->requestUri} HTTP/1.1", "Connection:" => "Upgrade", "Host:" => "{$this->host}", "Sec-WebSocket-Key:" => "{$this->handshakeChallenge}", "Origin:" => "{$this->origin}", "Sec-WebSocket-Version:" => 13, "Upgrade:" => "websocket");

        return $this->headers;
    }

    protected function buildHeaderArrayHixie76()
    {
        $this->hixieKey1 = WebSocketFunctions::randHixieKey();
        $this->hixieKey2 = WebSocketFunctions::randHixieKey();
        $this->headers = array("GET" => "{$this->requestUri} HTTP/1.1", "Connection:" => "Upgrade", "Host:" => "{$this->host}", "Origin:" => "{$this->origin}", "Sec-WebSocket-Key1:" => "{$this->hixieKey1->key}", "Sec-WebSocket-Key2:" => "{$this->hixieKey2->key}", "Upgrade:" => "websocket", "Sec-WebSocket-Protocol: " => "hiwavenet");

        return $this->headers;
    }

    private function serializeHeaders()
    {
        $str = '';

        foreach ($this->headers as $k => $v) {
            $str .= $k . " " . $v . "\r\n";
        }

        return $str . "\r\n";
    }

    # mamta: hixie 76

    public function addHeader($key, $value)
    {
        $this->headers[$key . ":"] = $value;
    }

    public function send($string)
    {
        $this->_connection->sendString($string);
    }

    public function sendMessage($msg)
    {
        $this->_connection->sendMessage($msg);
    }

    /**
     *
     * @return IWebSocketMessage
     */
    public function readMessage()
    {
        while (count($this->_messages) == 0)
            $this->readFrame();


        return array_shift($this->_messages);
    }

    /**
     * @return WebSocketFrame
     */
    public function readFrame()
    {
        $buffer = fread($this->socket, 8192);

        $this->_frames = array_merge($this->_frames, $this->_connection->readFrame($buffer));

        return array_shift($this->_frames);
    }

    public function close()
    {
        /**
         * @var WebSocketFrame
         */
        $frame = null;
        $this->sendFrame(WebSocketFrame::create(WebSocketOpcode::CloseFrame));

        $i = 0;
        do {
            $i++;
            $frame = @$this->readFrame();
        } while ($i < 2 && $frame && $frame->getType() == WebSocketOpcode::CloseFrame);

        @fclose($this->socket);
    }

    public function sendFrame(IWebSocketFrame $frame)
    {
        $this->_connection->sendFrame($frame);
    }

}
