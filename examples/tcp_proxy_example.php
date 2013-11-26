#!/php -q
<?php

require_once("../vendor/autoload.php");

// Run from command prompt > php demo.php
use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\WebSocketConnectionInterface;
use Devristo\Phpws\Server\UriHandler\WebSocketUriHandler;
use Devristo\Phpws\Server\WebSocketServer;

/**
 * This demo resource handler will respond to all messages sent to /echo/ on the socketserver below
 *
 * All this handler does is echoing the responds to the user
 * @author Chris
 *
 */
class ProxyHandler extends WebSocketUriHandler
{
    /**
     * @var \React\Stream\Stream[][]
     */
    protected $streams = array();
    protected $server;

    public function __construct(\React\EventLoop\LoopInterface $loop, $logger)
    {
        parent::__construct($logger);
        $this->loop = $loop;
    }

    public function onDisconnect(WebSocketConnectionInterface $user)
    {
        $this->logger->notice(sprintf("User %s has been removed from proxy", $user->getId()));
        foreach ($this->getStreamsByUser($user) as $stream) {
            $stream->close();
        }
        unset($this->streams[$user->getId()]);
    }

    public function onMessage(WebSocketConnectionInterface $user, WebSocketMessageInterface $msg)
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

    protected function requestConnect(WebSocketConnectionInterface $user, $message)
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

                $user->sendString(json_encode(array(
                    'connection' => $id,
                    'event' => 'connected',
                    'tag' => property_exists($message, 'tag') ? $message->tag : null
                )));

                $stream->on("data", function ($data) use ($stream, $id, $user, $logger){
                    $message = array(
                        'connection' => $id,
                        'event' => 'data',
                        'data' => $data
                    );

                    $user->sendString(json_encode($message));
                });

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

    protected function requestWrite(WebSocketConnectionInterface $user, $message)
    {
        $stream = $this->getStream($user, $message->connection);
        $this->logger->notice(sprintf("User %s writes %d bytes to connection %s", $user->getId(), strlen($message->data), $message->connection));
        $stream->write($message->data);
    }

    protected function requestClose(WebSocketConnectionInterface $user, $message)
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
     * @param WebSocketConnectionInterface $user
     * @param $id
     * @return \React\Stream\Stream
     */
    protected function getStream(WebSocketConnectionInterface $user, $id)
    {
        $userStreams = $this->getStreamsByUser($user);

        return array_key_exists($id, $userStreams) ? $userStreams[$id] : null;
    }

    /**
     * @param WebSocketConnectionInterface $user
     * @return \React\Stream\Stream[]
     */
    protected function getStreamsByUser(WebSocketConnectionInterface $user)
    {
        return array_key_exists($user->getId(), $this->streams) ? $this->streams[$user->getId()] : array();
    }

    protected function removeStream(WebSocketConnectionInterface $user, $id)
    {
        unset($this->streams[$user->getId()][$id]);
    }

    protected function addStream(WebSocketConnectionInterface $user, $id, \React\Stream\Stream $stream){
        $this->streams[$user->getId()][$id] = $stream;
    }
}

$loop = \React\EventLoop\Factory::create();
$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);
$router = new \Devristo\Phpws\Server\UriHandler\ClientRouter($server, $logger);
$router->addUriHandler("#^/proxy$#i", new ProxyHandler($loop, $logger));

$server->bind();
$loop->run();