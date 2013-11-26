Description
===========

WebSocket Server and Client library for PHP. Works with the latest HyBi specifications, as well the older Hixie #76 specification used by older Chrome versions and some Flash fallback solutions.

This project was started to bring more interactive features to http://www.u2start.com/

Downloads
---------
The current version available for download is 1.0 RC1. This version has been thouroughly tested. However documentation is still minimal. 

Features
---------
Server
  * Hixie #76 and Hybi #12 protocol versions
  * Flash client support (also serves XML policy file on the same port)
     * See https://github.com/gimite/web-socket-js for a compatible Flash Client
  * Native Firefox, Safari (iPod / iPhone as well), Chrome and IE10 support. With Flash Client every browser supporting Flash works as well (including IE6-9, Opera, Android and other older desktop browsers).
  * Opera (Mobile) supports WebSockets natively but support has been disabled by default. Can be enabled in opera:config.

Client
  * Hybi / Hixie76 support.


Known Issues
-------------
  * Lacks ORIGIN checking (can be implemented manually in onConnect using getHeaders(), just disconnect the user when you dont like the Origin header)
  * No support for extension data from the HyBi specs.

Requirements
-------------
*Server*
 * PHP 5.3
 * Open port for the server
 * PHP OpenSSL module to run a server over a encrypted connection

* Composer dependencies *
These will be installed automatically when using phpws as a composer package.

 * Reactphp
 * ZF2 Logger

*Client*
 * PHP 5.3
 * Server that implements the HyBi (#8-#12) draft version
 * PHP OpenSSL module to connect using SSL (wss:// uris)

Server Example
---------------
```php
require_once("vendor/autoload.php");            // Composer autoloader

use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\WebSocketConnectionInterface;
use Devristo\Phpws\Server\WebSocketServer;

$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

// Create a WebSocket server using SSL
$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);

$server->on("connect", function(WebSocketConnectionInterface $user){
    $user->sendString("Hey! I am the echo robot. I will repeat all your input!");
});

$server->on("message", function(WebSocketConnectionInterface $user, WebSocketMessageInterface $message) use($logger){
    $logger->notice(sprintf("We have got '%s' from client %s", $message->getData(), $user->getId()));
    $user->sendString($message->getData());
});

// Bind the server
$server->bind();

// Start the event loop
$loop->run();
```

Client Example
---------------------
```php
require_once("vendor/autoload.php");                // Composer autoloader

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
```
