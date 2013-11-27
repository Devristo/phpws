<?php

require_once("../vendor/autoload.php");

// Run from command prompt > php demo.php
use Devristo\Phpws\Messaging\JsonMessage;
use Devristo\Phpws\Protocol\JsonTransport;
use Devristo\Phpws\Protocol\ProtocolStack;
use Devristo\Phpws\Server\WebSocketServer;

$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new \Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);
$server->bind();

// Here we create a new protocol stack on top of WebSocketMessages
$stack = new ProtocolStack($server, array(new JsonTransport($loop, $logger)));
$stack->on("connect", function(JsonTransport $transport) use ($logger){
    $transport->whenResponseTo("hello world!", 0.1)->then(function(JsonMessage $result) use ($logger){
        $logger->notice(sprintf("Got '%s' in response to 'hello world!'", $result->getData()));
    });
});


// Start the event loop
$loop->run();