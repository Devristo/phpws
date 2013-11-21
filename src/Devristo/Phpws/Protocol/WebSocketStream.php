<?php

namespace Devristo\Phpws\Protocol;

use Devristo\Phpws\Messaging\IWebSocketMessage;
use Devristo\Phpws\Protocol\IWebSocketConnection;
use Devristo\Phpws\Protocol\WebSocketConnectionFactory;
use Devristo\Phpws\Protocol\WebSocketConnectionFlash;
use Devristo\Phpws\Server\ISocketStream;
use Exception;
use Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;

class WebSocketStream implements ISocketStream, LoggerAwareInterface
{

    private $_socket = null;

    protected $logger;

    /**
     *
     * @var IWebSocketConnection
     */
    private $_connection = null;
    private $_writeBuffer = '';
    private $_lastChanged = null;
    private $_disconnecting = false;
    private $_immediateWrite = false;

    /**
     *
     * Enter description here ...
     * @var \Devristo\Phpws\Protocol\WebSocketObserver[]
     */
    private $_observers = array();

    public function __construct(WebSocketObserver $server, $socket, $immediateWrite = false)
    {
        $this->_socket = $socket;
        $this->_lastChanged = time();
        $this->_immediateWrite = $immediateWrite;

        $this->addObserver($server);
    }

    public function onData($data)
    {
        try {
            $this->_lastChanged = time();

            if ($this->_connection)
                $this->_connection->readFrame($data);
            else
                $this->establishConnection($data);
        } catch (Exception $e) {
            $this->disconnect();
        }
    }

    public function setConnection(IWebSocketConnection $con)
    {
        $this->_connection = $con;
    }

    public function onMessage(IWebSocketMessage $m)
    {
        foreach ($this->_observers as $observer) {
            $observer->onMessage($this->getConnection(), $m);
        }
    }

    public function establishConnection($data)
    {
        $this->_connection = WebSocketConnectionFactory::fromSocketData($this, $data, $this->logger);

        if ($this->_connection instanceof WebSocketConnectionFlash)
            return;

        foreach ($this->_observers as $observer) {
            $observer->onConnectionEstablished($this);
        }
    }

    public function write($data)
    {
        $this->_writeBuffer .= $data;

        if ($this->_immediateWrite == true) {
            while ($this->_writeBuffer != '')
                $this->mayWrite();
        }
    }

    public function requestsWrite()
    {
        return strlen($this->_writeBuffer);
    }

    public function mayWrite()
    {
        $bytesWritten = fwrite($this->_socket, $this->_writeBuffer, strlen($this->_writeBuffer));

        if ($bytesWritten === false)
            $this->close();

        $this->_writeBuffer = substr($this->_writeBuffer, $bytesWritten);

        if (strlen($this->_writeBuffer) == 0 && $this->isClosing())
            $this->close();
    }

    public function getLastChanged()
    {
        return $this->_lastChanged;
    }

    public function onFlashXMLRequest(WebSocketConnectionFlash $connection)
    {
        foreach ($this->_observers as $observer) {
            $observer->onFlashXMLRequest($connection);
        }
    }

    public function disconnect()
    {
        $this->_disconnecting = true;

        if ($this->_writeBuffer == '')
            $this->close();
    }

    public function isClosing()
    {
        return $this->_disconnecting;
    }

    public function close()
    {
        fclose($this->_socket);
        foreach ($this->_observers as $observer) {
            $observer->onDisconnect($this);
        }
    }

    public function getSocket()
    {
        return $this->_socket;
    }

    /**
     *
     * @return IWebSocketConnection
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    public function addObserver(WebSocketObserver $s)
    {
        $this->_observers[] = $s;
    }

    public function acceptConnection()
    {
        throw new \BadMethodCallException();
    }

    public function isServer()
    {
        return false;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}