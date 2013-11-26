<?php
/**
 * Created by PhpStorm.
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
$client->on("connected", function($headers) use ($logger, $client){
    $logger->notice("Connected!");
    $client->send("Hello world!");
});

$client->on("message", function($message) use ($client, $logger){
    $logger->notice("Got message: ".$message->getData());
    $client->close();
});


$client->open();
$loop->run();