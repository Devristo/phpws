<?php

namespace Devristo\Phpws\Protocol;

use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\WebSocketConnectionInterface;
use Devristo\Phpws\Protocol\WebSocketConnectionFactory;
use Devristo\Phpws\Protocol\WebSocketConnectionFlash;
use Devristo\Phpws\Server\ISocketStream;
use Devristo\Phpws\Server\SocketStream;
use Devristo\Phpws\Server\WebSocketStream;
use Exception;
use React\EventLoop\LoopInterface;
use React\Socket\Connection;
use Zend\EventManager\Event;
use Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;

class WebSocketServerClient extends Connection
{
    protected $logger;

    /**
     *
     * @var WebSocketConnectionInterface
     */
    private $_connection = null;
    private $_lastChanged = null;

    public function __construct($socket, LoopInterface $loop, $logger)
    {
        parent::__construct($socket, $loop);

        $this->_lastChanged = time();
        $this->on("data", array($this, 'onData'));
        $this->logger = $logger;
    }

    public function handleData($stream)
    {
        $data = @fread($stream, $this->bufferSize);
        if ('' === $data || false === $data) {
            $this->end();
        } else {
            $this->emit('data', array($data, $this));
        }
    }

    public function onData($data)
    {
        try {
            $this->_lastChanged = time();

            if ($this->_connection)
                $this->_connection->onData($data);
            else
                $this->establishConnection($data);
        } catch (Exception $e) {
            $this->close();
        }
    }

    public function setConnection(WebSocketConnectionInterface $con)
    {
        $this->_connection = $con;
    }

    public function establishConnection($data)
    {
        $this->_connection = WebSocketConnectionFactory::fromSocketData($this, $data, $this->logger);
        $myself = $this;
        $this->_connection->on("message", function($message) use($myself){
            $myself->emit("message", array("message" => $message));
        });

        $this->_connection->on("flashXmlRequest", function($message) use($myself){
            $myself->emit("flashXmlRequest");
        });

        if ($this->_connection instanceof WebSocketConnectionFlash)
            return;

        $this->emit("connect");
    }

    public function getLastChanged()
    {
        return $this->_lastChanged;
    }

    /**
     *
     * @return WebSocketConnectionInterface
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}