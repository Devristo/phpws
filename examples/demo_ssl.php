#!/php -q
<?php

require_once("vendor/autoload.php");


// Run from command prompt > php demo.php
use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketOpcode;
use Devristo\Phpws\Messaging\IWebSocketMessage;
use Devristo\Phpws\Protocol\WebSocketConnectionInterface;
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

    public function onMessage(WebSocketConnectionInterface $user, IWebSocketMessage $msg) {
        $this->logger->notice("[ECHO] {$msg->getData()}");
        // Echo
        $user->sendMessage($msg);
    }

    public function onAdminMessage(WebSocketConnectionInterface $user, IWebSocketMessage $obj) {
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
class DemoSslSocketServer implements IWebSocketServerObserver {

    protected $debug = true;
    protected $server;

    public function __construct($loop) {
        $logger = new \Zend\Log\Logger();
        $logger->addWriter(new Zend\Log\Writer\Stream("php://output"));

        $this->logger = $logger;

        $this->server = new WebSocketServer("ssl://0.0.0.0:12345", $loop, $logger);
        $this->server->addObserver($this);

        $this->server->addUriHandler("echo", new ProxyHandler($logger));

        $this->setupSSL();
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

    public function onConnect(WebSocketConnectionInterface $user) {
        $this->logger->notice("[DEMO] {$user->getId()} connected");
    }

    public function onMessage(WebSocketConnectionInterface $user, IWebSocketMessage $msg) {
        $this->logger->notice("[DEMO] {$user->getId()} says '{$msg->getData()}'");
    }

    public function onDisconnect(WebSocketConnectionInterface $user) {
        $this->logger->notice("[DEMO] {$user->getId()} disconnected");
    }

    public function onAdminMessage(WebSocketConnectionInterface $user, IWebSocketMessage $msg) {
        $this->logger->notice("[DEMO] Admin Message received!");

        $frame = WebSocketFrame::create(WebSocketOpcode::PongFrame);
        $user->sendFrame($frame);
    }

    public function run() {
        $this->server->run();
    }

}

$loop = \React\EventLoop\Factory::create();

// Start server
$server = new ProxyWebSocketServer($loop);
$server->run();
$loop->run();