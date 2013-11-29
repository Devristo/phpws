#!/php -q
<?php

require_once("../vendor/autoload.php");

// Run from command prompt > php demo.php
use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\WebSocketTransportInterface;
use Devristo\Phpws\Server\UriHandler\WebSocketUriHandler;
use Devristo\Phpws\Server\WebSocketServer;

/**
 * This URI handler will allow clients to open TCP connections through the outside world. Phpws is then acting as proxy.
 *
 * @author Chris
 *
 */
class ProxyHandler extends WebSocketUriHandler
{
    /**
     * A multi-dimensional dictionary. First key is user id and second key is the id of the TCP stream. The value is the
     * TCP stream itself
     *
     * @var \React\Stream\Stream[][]
     */
    protected $streams = array();
    protected $server;

    /**
     * @param \React\EventLoop\LoopInterface $loop The React Loop, it is used to listen to events on newly created TCP
     * streams
     * @param $logger
     */
    public function __construct(\React\EventLoop\LoopInterface $loop, $logger)
    {
        parent::__construct($logger);
        $this->loop = $loop;
    }

    public function onDisconnect(WebSocketTransportInterface $user)
    {
        foreach ($this->getStreamsByUser($user) as $stream) {
            $stream->close();
        }
        unset($this->streams[$user->getId()]);
    }

    /**
     * Entry point for all messages received from clients in this proxy 'room'
     *
     * @param WebSocketTransportInterface $user
     * @param WebSocketMessageInterface $msg
     */
    public function onMessage(WebSocketTransportInterface $user, WebSocketMessageInterface $msg)
    {
        try {
            $message = json_decode($msg->getData());

            if ($message->command == 'connect')
                $this->requestConnect($user, $message);
            elseif ($message->command == 'write')
                $this->requestWrite($user, $message);
            elseif ($message->command == 'close')
                $this->requestClose($user, $message);

        } catch (Exception $e) {
            $this->logger->err($e->getMessage());
        }
    }

    /**
     * Handler called when a CONNECT message is sent by a client
     *
     * A React SocketClient will be created, Google DNS is used to resolve host names. When the connection is made
     * several event listeners are attached. When data is received on the stream, it is forwarded to the client requesting
     * the proxied TCP connection
     *
     * Other events forwarded are connect and close
     *
     * @param WebSocketTransportInterface $user
     * @param $message
     */
    protected function requestConnect(WebSocketTransportInterface $user, $message)
    {
        $address = $message->address;
        $this->logger->notice(sprintf("User %s requests connection to %s", $user->getId(), $address));

        try {
            $dnsResolverFactory = new React\Dns\Resolver\Factory();
            $dns = $dnsResolverFactory->createCached('8.8.8.8', $this->loop);
            $stream = new \React\SocketClient\Connector($this->loop, $dns);

            list($host, $port) = explode(":", $address);

            $logger = $this->logger;
            $that = $this;

            $stream->create($host, $port)->then(function (\React\Stream\Stream $stream) use($user, $logger, $message, $address, $that){
                $id = uniqid("stream-$address-");
                $that->addStream($user, $id, $stream);

                // Notify the user when the connection has been made
                $user->sendString(json_encode(array(
                    'connection' => $id,
                    'event' => 'connected',
                    'tag' => property_exists($message, 'tag') ? $message->tag : null
                )));

                // Forward data back to the user
                $stream->on("data", function ($data) use ($stream, $id, $user, $logger){
                    $logger->notice("Forwarding ".strlen($data). " bytes from stream $id to {$user->getId()}");
                    $message = array(
                        'connection' => $id,
                        'event' => 'data',
                        'data' => $data
                    );

                    $user->sendString(json_encode($message));
                });

                // When the stream closes, notify the user
                $stream->on("close", function() use($user, $id, $logger, $address){
                    $logger->notice(sprintf("Connection %s of user %s to %s has been closed", $id, $user->getId(), $address));

                    $message =
                        array(
                            'connection' => $id,
                            'event' => 'close'
                        );

                    $user->sendString(json_encode($message));
                });
            });
        } catch (Exception $e) {
            $user->sendString(json_encode(array(
                'event' => 'error',
                'tag' => property_exists($message, 'tag') ? $message->tag : null,
                'message' => $e->getMessage()
            )));
        }
    }

    /**
     * Forward data send by the user over the specified TCP stream
     *
     * @param WebSocketTransportInterface $user
     * @param $message
     */
    protected function requestWrite(WebSocketTransportInterface $user, $message)
    {
        $stream = $this->getStream($user, $message->connection);

        if($stream){
            $this->logger->notice(sprintf("User %s writes %d bytes to connection %s", $user->getId(), strlen($message->data), $message->connection));
            $stream->write($message->data);
        }
    }

    /**
     * Close the stream specified by the user
     *
     * @param WebSocketTransportInterface $user
     * @param $message
     */
    protected function requestClose(WebSocketTransportInterface $user, $message)
    {
        $stream = $this->getStream($user, $message->connection);

        if($stream){
            $this->logger->notice(sprintf("User %s closes connection %s", $user->getId(), $message->connection));
            $stream->close();
            $this->removeStream($user, $message->connection);

            $user->sendString(json_encode(array(
                'event' => 'close',
                'connection' => $message->connection,
                'tag' => property_exists($message, 'tag') ? $message->tag : null
            )));
        } else {
            $user->sendString(json_encode(array(
                'event' => 'error',
                'tag' => property_exists($message, 'tag') ? $message->tag : null,
                'message' => 'Connection was already closed'
            )));
        }
    }

    /**
     * @param WebSocketTransportInterface $user
     * @param $id
     * @return \React\Stream\Stream
     */
    protected function getStream(WebSocketTransportInterface $user, $id)
    {
        $userStreams = $this->getStreamsByUser($user);

        return array_key_exists($id, $userStreams) ? $userStreams[$id] : null;
    }

    /**
     * @param WebSocketTransportInterface $user
     * @return \React\Stream\Stream[]
     */
    protected function getStreamsByUser(WebSocketTransportInterface $user)
    {
        return array_key_exists($user->getId(), $this->streams) ? $this->streams[$user->getId()] : array();
    }

    protected function removeStream(WebSocketTransportInterface $user, $id)
    {
        unset($this->streams[$user->getId()][$id]);
    }

    protected function addStream(WebSocketTransportInterface $user, $id, \React\Stream\Stream $stream){
        $this->streams[$user->getId()][$id] = $stream;
    }
}

$loop = \React\EventLoop\Factory::create();
$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);
$router = new \Devristo\Phpws\Server\UriHandler\ClientRouter($server, $logger);
$router->addRoute("#^/proxy$#i", new ProxyHandler($loop, $logger));

$server->bind();
$loop->run();