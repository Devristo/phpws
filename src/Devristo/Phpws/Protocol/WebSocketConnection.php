<?php

namespace Devristo\Phpws\Protocol;

use Exception;
use React\EventLoop\LoopInterface;
use React\Socket\Connection;
use Zend\Http\Request;
use Zend\Log\LoggerInterface;

class WebSocketConnection extends Connection
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     *
     * @var WebSocketTransportInterface
     */
    private $_transport = null;
    private $_lastChanged = null;

    public function __construct($socket, LoopInterface $loop, $logger)
    {
        parent::__construct($socket, $loop);

        $this->_lastChanged = time();
        $this->logger = $logger;
    }

    public function handleData($stream)
    {
        if (feof($stream) || !is_resource($stream)){
            $this->close();
            return;
        }

        $data = fread($stream, $this->bufferSize);
        if ('' === $data || false === $data) {
            $this->close();
        } else {
            $this->onData($data);
        }
    }

    private function onData($data)
    {
        try {
            $this->_lastChanged = time();

            if ($this->_transport)
                $this->emit('data', array($data, $this));
            else
                $this->establishConnection($data);
        } catch (Exception $e) {
            $this->logger->err("Error while handling incoming data. Exception message is: ".$e->getMessage());
            $this->close();
        }
    }

    public function setTransport(WebSocketTransportInterface $con)
    {
        $this->_transport = $con;
    }

    public function establishConnection($data)
    {
        $this->_transport = WebSocketTransportFactory::fromSocketData($this, $data, $this->logger);
        $myself = $this;

        $this->_transport->on("handshake", function(Handshake $request) use ($myself){
            $myself->emit("handshake", array($request));
        });

        $this->_transport->on("connect", function() use ($myself){
            $myself->emit("connect", array($myself));
        });

        $this->_transport->on("message", function($message) use($myself){
            $myself->emit("message", array("message" => $message));
        });

        $this->_transport->on("flashXmlRequest", function($message) use($myself){
            $myself->emit("flashXmlRequest");
        });

        if ($this->_transport instanceof WebSocketTransportFlash)
            return;

        $request = Request::fromString($data);
        $this->_transport->respondTo($request);
    }

    public function getLastChanged()
    {
        return $this->_lastChanged;
    }

    /**
     *
     * @return WebSocketTransportInterface
     */
    public function getTransport()
    {
        return $this->_transport;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}