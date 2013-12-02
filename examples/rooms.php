<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 27-11-13
 * Time: 18:00
 */
require_once("../vendor/autoload.php");

// Run from command prompt > php demo.php
use Devristo\Phpws\RemoteEvent\RemoteEventTransport;
use Devristo\Phpws\Protocol\StackTransport;
use Devristo\Phpws\Protocol\TransportInterface;
use Devristo\Phpws\Server\WebSocketServer;

$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

// Create a WebSocket server and create a router which sends all user requesting /echo to the DemoEchoHandler above
$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);

$handler = new \Devristo\Phpws\RemoteEvent\RemoteEvents($logger);

$server->on("connect", function(TransportInterface $transport) use($loop, $logger, $handler){

    $stack = StackTransport::create($transport, array(
        function (TransportInterface $transport) use ($loop, $logger) {
            return new RemoteEventTransport($transport, $loop, $logger);
        }
    ));

    $handler->listenTo($stack);
});

$handler->room("time")->on("subscribe", function (StackTransport $transport) use ($logger, $handler){
    $logger->notice("Someone joined our room full of time enthousiasts!!");
});

// Each 0.5 seconds sent the time to all connected clients
$loop->addPeriodicTimer(0.5, function() use($server, $handler, $logger){
    $time = new DateTime();
    $string = $time->format("Y-m-d H:i:s");

    if(count($handler->room("time")->getMembers()))
        $logger->notice("Broadcasting time to time room: $string");

    $handler->room("time")->remoteEmit("time", $string);
});


// Bind the server
$server->bind();

// Start the event loop
$loop->run();