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

class TcpStream implements ISocketStream{
    protected $websocket;
    protected $socket;

    protected $id;

    protected $writeBuffer;
    protected $closing = false;
    protected $closed = false;

    public function __construct(SocketServer $server, $address, IWebSocketConnection $connection, \Zend\Log\LoggerInterface $logger){
        $this->id = uniqid("tcp-$address-");

        $this->address = $address;
        $this->socketServer = $server;
        $this->logger = $logger;
        $this->socket = stream_socket_client("tcp://$address", $error_number, $error, 5, STREAM_CLIENT_CONNECT);
        $server->attachStream($this);

        if(!$this->socket)
            throw new BadMethodCallException("Cannot connect to $address");

        $this->websocket = $connection;
    }

    public function getId(){
        return $this->id;
    }

    public function onData($data)
    {
        $this->logger->notice(sprintf("Got %d bytes on %s from %s proxying to user %s", strlen($data), $this->getId(), $this->getAddress(), $this->websocket->getId()));
        $message =
            [
                'connection'    => $this->getId(),
                'event'         => 'data',
                'data'          => $data
            ];

        $this->websocket->sendString(json_encode($message));
    }

    public function close()
    {
        $this->logger->debug(sprintf("Closing connection %s to %s", $this->getId(), $this->address));
        @fclose($this->getSocket());
        $this->writeBuffer = '';

        $message =
            [
                'connection'    => $this->getId(),
                'event'         => 'close'
            ];

        $this->websocket->sendString(json_encode($message));
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
}