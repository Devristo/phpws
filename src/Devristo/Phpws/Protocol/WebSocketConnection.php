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
    private $transport;
    private $lastChanged;

    /**
     * @param $socket
     * @param LoopInterface $loop
     * @param $logger
     */
    public function __construct($socket, LoopInterface $loop, $logger)
    {
        parent::__construct($socket, $loop);

        $this->lastChanged = time();
        $this->logger = $logger;
    }

    /**
     * @param $stream
     */
    public function handleData($stream)
    {
        if (feof($stream) || !is_resource($stream)) {
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

    /**
     * @param $data
     */
    private function onData($data)
    {
        try {
            $this->lastChanged = time();

            if ($this->transport) {
                $this->emit('data', [$data, $this]);
            } else {
                $this->establishConnection($data);
            }
        } catch (Exception $e) {
            $this->logger->err("Error while handling incoming data. Exception message is: " . $e->getMessage());
            $this->close();
        }
    }

    /**
     * @param WebSocketTransportInterface $con
     */
    public function setTransport(WebSocketTransportInterface $con)
    {
        $this->transport = $con;
    }

    /**
     * @param $data
     */
    public function establishConnection($data)
    {
        $this->transport = WebSocketTransportFactory::fromSocketData($this, $data, $this->logger);
        $myself = $this;

        $this->transport->on("handshake", function (Handshake $request) use ($myself) {
            $myself->emit("handshake", [$request]);
        });

        $this->transport->on("connect", function () use ($myself) {
            $myself->emit("connect", [$myself]);
        });

        $this->transport->on("message", function ($message) use ($myself) {
            $myself->emit("message", ["message" => $message]);
        });

        $this->transport->on("flashXmlRequest", function ($message) use ($myself) {
            $myself->emit("flashXmlRequest");
        });

        if ($this->transport instanceof WebSocketTransportFlash) {
            return;
        }

        $request = Request::fromString($data);
        $this->transport->respondTo($request);
    }

    /**
     * @return int
     */
    public function getLastChanged()
    {
        return $this->lastChanged;
    }

    /**
     *
     * @return WebSocketTransportInterface
     */
    public function getTransport()
    {
        return $this->transport;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
