#!/php -q
<?php

require_once(__DIR__."/vendor/autoload.php");


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
class DemoEchoHandler extends WebSocketUriHandler {

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        $this->logger->notice("[ECHO] " . strlen($msg->getData()) . " bytes");
        // Echo
        $user->sendMessage($msg);
    }

    public function onAdminMessage(IWebSocketConnection $user, IWebSocketMessage $obj) {
        $this->logger->notice("[DEMO] Admin TEST received!");

        $frame = WebSocketFrame::create(WebSocketOpcode::PongFrame);
        $user->sendFrame($frame);
    }

}

/**
 * Demo socket server. Implements the basic eventlisteners and attaches a resource handler for /echo/ urls.
 *
 *
 * @author Chris
 *
 */
class DemoSocketServer implements IWebSocketServerObserver {

    protected $debug = true;
    protected $server;

    public function __construct() {
        $logger = new \Zend\Log\Logger();
        $logger->addWriter(new Zend\Log\Writer\Stream("php://output"));

        $this->logger = $logger;

        $this->server = new WebSocketServer("tcp://0.0.0.0:12345", $logger);
        $this->server->addObserver($this);

        $this->server->addUriHandler("echo", new DemoEchoHandler($logger));
    }

    public function onConnect(IWebSocketConnection $user) {
        $this->logger->notice("[DEMO] {$user->getId()} connected");
    }

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg) {
        //$this->logger->notice("[DEMO] {$user->getId()} says '{$msg->getData()}'");
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
$server = new DemoSocketServer();
$server->run();