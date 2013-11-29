<?php

require_once("../vendor/autoload.php");

// Run from command prompt > php demo.php
use Devristo\Phpws\Messaging\JsonMessage;
use Devristo\Phpws\Protocol\StackTransport;
use Devristo\Phpws\Protocol\JsonTransport;
use Devristo\Phpws\Protocol\ServerProtocolStack;
use Devristo\Phpws\Protocol\TransportInterface;
use Devristo\Phpws\Server\WebSocketServer;


class StackHandler extends \Devristo\Phpws\Server\UriHandler\WebSocketUriHandler{
    /**
     * Notify everyone when a user has joined the chat
     *
     * @param StackTransport $stackTransport
     */
    public function onConnect(\Devristo\Phpws\Protocol\WebSocketTransportInterface $stackTransport){
        /**
         * @var $stackTransport StackTransport
         * @var $jsonTransport JsonTransport
         */
        $jsonTransport = $stackTransport->getTopTransport();
        $logger = $this->logger;

        $server = $stackTransport->getHandshakeResponse()->getHeaders()->get('X-WebSocket-Server')->getFieldValue();

        $jsonTransport->whenResponseTo("hello world from $server!", 0.1)->then(function(JsonMessage $result) use ($logger, $server){
            $logger->notice(sprintf("Got '%s' in response to 'hello world from $server!'", $result->getData()));
        });
    }
}

$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new \Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);
$server->bind();

$server->on("handshake", function(\Devristo\Phpws\Protocol\WebSocketTransportInterface $transport, \Devristo\Phpws\Protocol\Handshake $handshake){
    $handshake->getResponse()->getHeaders()->addHeaderLine("X-WebSocket-Server", "phpws");
});

// Here we create a new protocol stack on top of WebSocketMessages
$stack = new ServerProtocolStack($server, array(
    function(TransportInterface $carrier) use ($loop, $logger){
        return new JsonTransport($carrier, $loop, $logger);
    }
));

$router = new \Devristo\Phpws\Server\UriHandler\ClientRouter($stack, $logger);
$router->addUriHandler('#^/stack#i', new StackHandler($logger));

// Start the event loop
$loop->run();