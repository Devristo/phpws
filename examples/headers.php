#!/php -q
<?php
require_once("../vendor/autoload.php");
use Devristo\Phpws\Protocol\Handshake;
use Devristo\Phpws\Protocol\TransportInterface;
use Devristo\Phpws\Protocol\WebSocketTransport;
use Devristo\Phpws\Server\WebSocketServer;

$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

// Create a WebSocket server using SSL
$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);
$server->on("handshake", function(WebSocketTransport $client, Handshake $handshake){
    // Here we can alter or abort PHPWS's response to the user
    $handshake->getResponse()->getHeaders()->addHeaderLine("X-WebSocket-Server", "phpws");

    // We can also see which headers the client sent in its handshake. Lets proof it
    $userAgent = $handshake->getRequest()->getHeader('User-Agent')->getFieldValue();
    $handshake->getResponse()->getHeaders()->addHeaderLine("X-User-Agent",$userAgent);

    // Since we cannot see in the browser what headers were sent by the server, we will send them again as a message
    $client->on("connect", function() use ($client){

        // The request and the response is available on the transport object as well.
        $client->sendString($client->getHandshakeResponse()->toString());
    });
});

// Bind the server
$server->bind();

// Start the event loop
$loop->run();