<?php
use Devristo\Phpws\Protocol\IWebSocketConnection;
use Devristo\Phpws\Server\ISocketStream;
use Devristo\Phpws\Server\SocketServer;

/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 22-11-13
 * Time: 21:32
 */

class TcpStream implements ISocketStream, \Zend\EventManager\EventManagerAwareInterface{
    protected $socket;

    protected $id;

    protected $writeBuffer;
    protected $closing = false;
    protected $closed = false;

    public function __construct(SocketServer $server, $address, \Zend\Log\LoggerInterface $logger){
        $this->id = uniqid("tcp-$address-");

        $this->address = $address;
        $this->socketServer = $server;
        $this->logger = $logger;
        $this->socket = stream_socket_client("tcp://$address", $error_number, $error, 5, STREAM_CLIENT_CONNECT);
        $server->attachStream($this);

        if(!$this->socket)
            throw new BadMethodCallException("Cannot connect to $address");

        $this->_eventManager = new \Zend\EventManager\EventManager(__CLASS__);
    }

    public function getId(){
        return $this->id;
    }

    public function onData($data)
    {
        $this->_eventManager->trigger('data', $this, array(
            'data' => $data
        ));
    }

    public function close($triggerEvent=true)
    {
        if(!$this->isClosed()){
            $this->logger->debug(sprintf("Closing connection %s to %s", $this->getId(), $this->address));
            @fclose($this->getSocket());
            $this->closed = true;
            $this->writeBuffer = '';

            $this->_eventManager->trigger('close', $this);
        }
    }

    public function mayWrite()
    {
        $bytesWritten = fwrite($this->getSocket(), $this->writeBuffer, strlen($this->writeBuffer));

        if ($bytesWritten === false)
            $this->close();

        $this->writeBuffer = substr($this->writeBuffer, $bytesWritten);

        if (strlen($this->writeBuffer) == 0 && $this->isClosing())
            $this->close();
    }

    public function requestsWrite()
    {
        return !$this->closed && strlen($this->writeBuffer) > 0;
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function acceptConnection()
    {
        throw new BadMethodCallException();
    }

    public function isServer()
    {
        return false;
    }

    public function write($data)
    {
        if(!$this->closed)
            $this->writeBuffer .= $data;
    }

    public function requestClose()
    {
        if($this->requestsWrite())
            $this->closing = true;
        else $this->close();
    }

    public function isClosing()
    {
        return $this->closing;
    }

    public function isClosed(){
        return $this->closed;
    }

    public function getAddress(){
        return $this->address;
    }

    /**
     * Inject an EventManager instance
     *
     * @param \Zend\EventManager\EventManagerInterface $eventManager
     * @return void
     */
    public function setEventManager(\Zend\EventManager\EventManagerInterface $eventManager)
    {
        $this->_eventManager = $eventManager;
    }

    /**
     * Retrieve the event manager
     *
     * Lazy-loads an EventManager instance if none registered.
     *
     * @return \Zend\EventManager\EventManagerInterface
     */
    public function getEventManager()
    {
        return $this->_eventManager;
    }
}