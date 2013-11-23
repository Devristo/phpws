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

/**
 * This demo resource handler will respond to all messages sent to /echo/ on the socketserver below
 *
 * All this handler does is echoing the responds to the user
 * @author Chris
 *
 */
class DemoSslEchoHandler extends WebSocketUriHandler {
    /**
     * @var TcpStream[]
     */
    public $streams = [];

    protected $server;

    public function __construct(\Devristo\Phpws\Server\SocketServer $server, $logger){
        parent::__construct($logger);
        $this->socketServer = $server;
    }

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        try{
            $message = json_decode($msg->getData());

            if($message->command == 'connect')
                $this->requestConnect($user, $message);
            elseif($message->command == 'write')
                $this->requestWrite($user, $message);
            elseif($message->command == 'close')
                $this->requestClose($user, $message);

        } catch(Exception $e){
            $this->logger->err($e->getMessage());
        }
    }

    protected function requestConnect(IWebSocketConnection $user, $message){
        $address = $message->address;

        try{
            $stream = new TcpStream($this->socketServer, $address, $user);

            $this->streams[$stream->getId()] = $stream;

            $user->sendString(json_encode([
                'connection'        => $stream->getId(),
                'event'             => 'connected',
                'tag'               => $message->tag ?: null
            ]));
        } catch(Exception $e){
            $user->sendString(json_encode([
                'connection'        => $stream->getId(),
                'event'             => 'error',
                'tag'               => $message->tag ?: null,
                'message'           => $e->getMessage()
            ]));
        }
    }

    protected function requestWrite(IWebSocketConnection $user, $message){
        $stream = $this->streams[$message->connection];
        $stream->write($message->data);
    }

    protected function requestClose(IWebSocketConnection $user, $message){
        $stream = $this->streams[$message->connection];
        $stream->requestClose();
    }
}

/**
 * Demo socket server. Implements the basic eventlisteners and attaches a resource handler for /echo/ urls.
 *
 *
 * @author Chris
 *
 */
class DemoSslSocketServer implements IWebSocketServerObserver {

    protected $debug = true;
    protected $server;

    public function __construct() {
        $logger = new \Zend\Log\Logger();
        $logger->addWriter(new Zend\Log\Writer\Stream("php://output"));

        $this->logger = $logger;

        $this->server = new WebSocketServer("tcp://0.0.0.0:12345", $logger);
        $this->server->addObserver($this);

        $this->server->addUriHandler("echo", new DemoSslEchoHandler($this->server->_server,$logger));

//        $this->setupSSL();
    }

    private function getPEMFilename() {
        return './democert.pem';
    }

    public function setupSSL() {
        $context = stream_context_create();

        // local_cert must be in PEM format
        stream_context_set_option($context, 'ssl', 'local_cert', $this->getPEMFilename());

        stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
        stream_context_set_option($context, 'ssl', 'verify_peer', false);

        $this->server->setStreamContext($context);
    }

    public function onConnect(IWebSocketConnection $user) {
        $this->logger->notice("[DEMO] {$user->getId()} connected");
    }

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        $this->logger->notice("[DEMO] {$user->getId()} says '{$msg->getData()}'");
    }

    public function onDisconnect(IWebSocketConnection $user) {
        $this->logger->notice("[DEMO] {$user->getId()} disconnected");
    }

    public function onAdminMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        $this->logger->notice("[DEMO] Admin Message received!");

        $frame = WebSocketFrame::create(WebSocketOpcode::PongFrame);
        $user->sendFrame($frame);
    }

    public function run() {
        $this->server->run();
    }

}

// Start server
$server = new DemoSslSocketServer();
$server->run();
