<?php
/**
 * Connect to the echo.websocket.org server and check whether our message is echoed back
 *
 * User: Chris
 * Date: 30-9-13
 * Time: 21:05
 */

require_once(__DIR__ . "/../vendor/autoload.php");

$loop = \React\EventLoop\Factory::create();

$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

$client = new \Devristo\Phpws\Client\WebSocket("ws://echo.websocket.org/?encoding=text", $loop, $logger);
//$client = new \Devristo\Phpws\Client\WebSocket("ws://google.com", $loop, $logger);
$client->on("connect", function() use ($logger, $client){
    $logger->notice("Or we can use the connect event!");
    $client->send("Hello world!");
});

$client->on("message", function($message) use ($client, $logger){
    $logger->notice("Got message: ".$message->getData());
    $client->close();
});

$client->open()->then(function() use($logger, $client){
    $logger->notice("We can use a promise to determine when the socket has been connected!");
});

$loop->run();