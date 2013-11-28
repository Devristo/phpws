#!/php -q
<?php
require_once("../vendor/autoload.php");

use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\WebSocketTransportInterface;
use Devristo\Phpws\Server\WebSocketServer;

$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

// Create a WebSocket server using SSL
$server = new WebSocketServer("ssl://0.0.0.0:12345", $loop, $logger);
$context = stream_context_create();
stream_context_set_option($context, 'ssl', 'local_cert', "democert.pem");
stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
stream_context_set_option($context, 'ssl', 'verify_peer', false);
$server->setStreamContext($context);

// Sent a welcome message when a client connects
$server->on("connect", function(WebSocketTransportInterface $user){
    $user->sendString("Hey! I am the echo robot. I will repeat all your input!");
});

// Echo back any message the user sends
$server->on("message", function(WebSocketTransportInterface $user, WebSocketMessageInterface $message) use($logger){
    $logger->notice(sprintf("We have got '%s' from client %s", $message->getData(), $user->getId()));
    $user->sendString($message->getData());
});

// Bind the server
$server->bind();

// Start the event loop
$loop->run();