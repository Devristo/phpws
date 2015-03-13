WebSocket Server and Client library for PHP. Works with the latest HyBi specifications, as well the older Hixie #76 specification used by older Chrome versions and some Flash fallback solutions.

This project was started to bring more interactive features to http://www.u2start.com/

Features
============
Server
  * Hixie #76 and Hybi #12 protocol versions
  * Flash client support (also serves XML policy file on the same port)
     * See https://github.com/gimite/web-socket-js for a compatible Flash Client
  * Native Firefox, Safari (iPod / iPhone as well), Chrome and IE10 support. With Flash Client every browser supporting Flash works as well (including IE6-9, Opera, Android and other older desktop browsers).
  * Opera (Mobile) supports WebSockets natively but support has been disabled by default. Can be enabled in opera:config.

Client
  * Hybi / Hixie76 support.
  * Event-based Async I/O


Getting started
=================
The easiest way to set up PHPWS is by using it as Composer dependency. Add the following to your composer.json

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/Devristo/phpws"
        }
    ],
    "require": {
        "devristo/phpws": "dev-master"
    }
}
```

And run ```php composer.phar install```

To verify it is working create a time.php in your project root
```php
require_once("vendor/autoload.php");
use Devristo\Phpws\Server\WebSocketServer;

$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

// Create a WebSocket server using SSL
$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);

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
```

And a client time.html as follows
```html
<html>
    <head>
        <title>WebSocket TEST</title>
    </head>
    <body>
        <h1>Server Time</h1>
        <strong id="time"></strong>

        <script>
            var socket = new WebSocket("ws://localhost:12345/");
            socket.onmessage = function(msg) {
                document.getElementById("time").innerText = msg.data;
            };
        </script>
    </body>
</html>
```
Now run the time.php from the command line and open time.html in your browser. You should see the current time, broadcasted
by phpws at regular intervals. If this works you might be interested in more complicated servers in the examples folder.

Getting started with the Phpws Client
=======================================
The following is a client for the websocket server hosted at http://echo.websocket.org

```php
require_once("vendor/autoload.php");                // Composer autoloader

$loop = \React\EventLoop\Factory::create();

$logger = new \Zend\Log\Logger();
$writer = new Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

$client = new \Devristo\Phpws\Client\WebSocket("ws://echo.websocket.org/?encoding=text", $loop, $logger);

$client->on("request", function($headers) use ($logger){
    $logger->notice("Request object created!");
});

$client->on("handshake", function() use ($logger) {
    $logger->notice("Handshake received!");
});

$client->on("connect", function($headers) use ($logger, $client){
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


Known Issues
==================
  * Lacks ORIGIN checking (can be implemented manually in onConnect using getHeaders(), just disconnect the user when you dont like the Origin header)
  * No support for extension data from the HyBi specs.

Requirements
=================
*Server*
 * PHP 5.4
 * Open port for the server
 * PHP OpenSSL module to run a server over a encrypted connection
 * http://pecl.php.net/package/pecl_http as its a dependency of Zend\Uri

* Composer dependencies *
These will be installed automatically when using phpws as a composer package.

 * Reactphp
 * ZF2 Logger

*Client*
 * PHP 5.4
 * Server that implements the HyBi (#8-#12) draft version
 * PHP OpenSSL module to connect using SSL (wss:// uris)

