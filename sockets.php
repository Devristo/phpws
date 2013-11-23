#!/php -q
<?php

require_once("vendor/autoload.php");
require_once("TcpStream.php");


// Run from command prompt > php demo.php
use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketOpcode;
use Devristo\Phpws\Messaging\IWebSocketMessage;
use Devristo\Phpws\Protocol\IWebSocketConnection;
use Devristo\Phpws\Server\IWebSocketServerObserver;
use Devristo\Phpws\Server\UriHandler\WebSocketUriHandler;
use Devristo\Phpws\Server\WebSocketServer;
use Devristo\Phpws\Utilities\DefaultDict;

/**
 * This demo resource handler will respond to all messages sent to /echo/ on the socketserver below
 *
 * All this handler does is echoing the responds to the user
 * @author Chris
 *
 */
class DemoSslEchoHandler extends WebSocketUriHandler
{
    /**
     * @var TcpStream[][]
     */
    protected $streams;
    protected $server;

    /**
     * @param IWebSocketConnection $user
     * @param $id
     * @return TcpStream|null
     */
    protected function getStream(IWebSocketConnection $user, $id)
    {
        $userStreams = $this->getStreamsByUser($user);

        return array_key_exists($id, $userStreams) ? $userStreams[$id] : null;
    }

    /**
     * @param IWebSocketConnection $user
     * @return TcpStream[]
     */
    protected function getStreamsByUser(IWebSocketConnection $user)
    {
        return $this->streams[$user->getId()];
    }

    protected function removeStream(IWebSocketConnection $user, TcpStream $stream)
    {
        unset($this->streams[$user->getId()][$stream->getId()]);
    }

    public function __construct(\Devristo\Phpws\Server\SocketServer $server, $logger)
    {
        parent::__construct($logger);
        $this->streams = new DefaultDict(array());
        $this->socketServer = $server;
    }

    public function onDisconnect(IWebSocketConnection $user)
    {
        $this->logger->notice(sprintf("User %s has been removed from proxy", $user->getId()));
        foreach ($this->getStreamsByUser($user) as $stream) {
            $stream->close();
        }
        unset($this->streams[$user->getId()]);
    }

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg)
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

    protected function requestConnect(IWebSocketConnection $user, $message)
    {
        $address = $message->address;
        $this->logger->notice(sprintf("User %s requests connection to %s", $user->getId(), $address));

        try {
            $stream = new TcpStream($this->socketServer, $address, $this->logger);
            $uriHandler = $this;

            $stream->getEventManager()->attach("data", function (\Zend\EventManager\Event $event) use ($user, $uriHandler) {
                $stream = $event->getTarget();
                $data = $event->getParam('data');

                $uriHandler->logger->notice(sprintf("Proxying %d bytes on %s from %s to user %s", strlen($data), $stream->getId(), $stream->getAddress(), $user->getId()));
                $message = array(
                        'connection' => $stream->getId(),
                        'event' => 'data',
                        'data' => $event->getParam('data')
                );

                $user->sendString(json_encode($message));
            });

            $stream->getEventManager()->attach("close", function (\Zend\EventManager\Event $event) use ($user, $uriHandler) {
                /**
                 * @var $stream TcpStream
                 */
                $stream = $event->getTarget();

                $uriHandler->logger->notice(sprintf("Connection %s of user %s to %s has been closed", $stream->getId(), $user->getId(), $stream->getAddress()));

                $message =
                    array(
                        'connection' => $stream->getId(),
                        'event' => 'close'
                    );

                $user->sendString(json_encode($message));

                // Remove stream from the user's list
                $uriHandler->removeStream($user, $stream);
            });

            $this->streams[$user->getId()][$stream->getId()] = $stream;

            $user->sendString(json_encode(array(
                'connection' => $stream->getId(),
                'event' => 'connected',
                'tag' => property_exists($message, 'tag') ? $message->tag : null
            )));
        } catch (Exception $e) {
            $user->sendString(json_encode(array(
                'event' => 'error',
                'tag' => property_exists($message, 'tag') ? $message->tag : null,
                'message' => $e->getMessage()
            )));
        }
    }

    protected function requestWrite(IWebSocketConnection $user, $message)
    {
        $stream = $this->getStream($user, $message->connection);
        $this->logger->notice(sprintf("User %s writes %d bytes to connection %s to %s", $user->getId(), strlen($message->data), $stream->getId(), $stream->getAddress()));
        $stream->write($message->data);
    }

    protected function requestClose(IWebSocketConnection $user, $message)
    {
        $stream = $this->getStream($user, $message->connection);

        if($stream){
            $this->logger->notice(sprintf("User %s closes connection %s to %s", $user->getId(), $stream->getId(), $stream->getAddress()));
            $stream->requestClose();
            $this->removeStream($user, $stream);

            $user->sendString(json_encode(array(
                'event' => 'close',
                'connection' => $stream->getId(),
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
}

/**
 * Demo socket server. Implements the basic eventlisteners and attaches a resource handler for /echo/ urls.
 *
 *
 * @author Chris
 *
 */
class DemoSslSocketServer extends \Devristo\Phpws\Server\WebSocketServerObserver
{

    protected $debug = true;
    protected $server;

    public function __construct()
    {
        $logger = new \Zend\Log\Logger();
        $writer = new Zend\Log\Writer\Stream("php://output");
        $writer->addFilter(new Zend\Log\Filter\Priority(\Zend\Log\Logger::NOTICE));
        $logger->addWriter($writer);
        $this->logger = $logger;

        $this->server = new WebSocketServer("tcp://0.0.0.0:12345", $logger);
        $this->server->addObserver($this);

        $this->server->addUriHandler("proxy", new DemoSslEchoHandler($this->server->_server, $logger));
    }

    private function getPEMFilename()
    {
        return './democert.pem';
    }

    public function setupSSL()
    {
        $context = stream_context_create();

        // local_cert must be in PEM format
        stream_context_set_option($context, 'ssl', 'local_cert', $this->getPEMFilename());

        stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
        stream_context_set_option($context, 'ssl', 'verify_peer', false);

        $this->server->setStreamContext($context);
    }

    public function run()
    {
        $this->server->run();
    }

}

// Start server
$server = new DemoSslSocketServer();
$server->run();
