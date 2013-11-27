#!/php -q
<?php
require_once("../vendor/autoload.php");
use Devristo\Phpws\Server\WebSocketServer;

$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

// Create a WebSocket server using SSL
$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);

// Each 0.5 seconds sent the time to all connected clients
$loop->addPeriodicTimer(0.5, function() use($server, $logger){
    $time = new DateTime();
    $string = $time->format("Y-m-d H:i:s");
    $logger->notice("Broadcasting time to all clients: $string");
    foreach($server->getConnections() as $client)
        $client->sendString($string);
});


// Bind the server
$server->bind();

// Start the event loop
$loop->run();