#!/php -q
<?php

require_once("../vendor/autoload.php");


// Run from command prompt > php demo.php
use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketOpcode;
use Devristo\Phpws\Messaging\WebSocketMessageInterface;
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
class DemoEchoHandler extends WebSocketUriHandler {


    public function onMessage(WebSocketConnectionInterface $user, WebSocketMessageInterface $msg) {
        $this->logger->notice("[ECHO] " . strlen($msg->getData()) . " bytes");
        // Echo
        $user->sendMessage($msg);
    }
}

$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

// Create a WebSocket server and create a router which sends all user requesting /echo to the DemoEchoHandler above
$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);
$router = new \Devristo\Phpws\Server\UriHandler\ClientRouter($server, $logger);
$router->addUriHandler('#^/echo$#i', new DemoEchoHandler($logger));

// Bind the server
$server->bind();

// Start the event loop
$loop->run();