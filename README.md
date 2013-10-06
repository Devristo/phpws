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
  * SSL support not well field tested.
  * Lacks ORIGIN checking (can be implemented manually in onConnect using getHeaders(), just disconnect the user when you dont like the Origin header)
  * No support for extension data from the HyBi specs.

Requirements
-------------
*Server*
 * PHP 5.3
 * Open port for the server
 * PHP OpenSSL module to run a server over a encrypted connection

*Client*
 * PHP 5.3
 * Server that implements the HyBi (#8-#12) draft version
 * PHP OpenSSL module to connect using SSL (wss:// uris)

Server Example
---------------
```php
	#!/php -q
	<?php

	// Run from command prompt > php demo.php

	/**
	 * This demo resource handler will respond to all messages sent to /echo/ on the socketserver below
	 *
	 * All this handler does is echoing the responds to the user
	 * @author Chris
	 *
	 */
	class DemoEchoHandler extends WebSocketUriHandler{
		public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg){
			echo "[ECHO] {$msg->getData()}\n";
			// Echo
			$user->sendMessage($msg);
		}

		public function onAdminMessage(IWebSocketConnection $user, IWebSocketMessage $obj){
			echo "[DEMO] Admin TEST received!\n";

			$frame = WebSocketFrame::create(WebSocketOpcode::PongFrame);
			$user->sendFrame($frame);
		}
	}

	/**
	 * Demo socket server. Implements the basic eventlisteners and attaches a resource handler for /echo/ urls.
	 *
	 *
	 * @author Chris
	 *
	 */
	class DemoSocketServer implements IWebSocketServerObserver{
		protected $debug = true;
		protected $server;

		public function __construct(){
			$this->server = new WebSocketServer('tcp://0.0.0.0:12345', 'superdupersecretkey');
			$this->server->addObserver($this);

			$this->server->addUriHandler("echo", new DemoEchoHandler());
		}

		public function onConnect(IWebSocketConnection $user){
			echo "[DEMO] {$user->getId()} connected\n";
		}

		public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg){
			echo "[DEMO] {$user->getId()} says '{$msg->getData()}'\n";
		}

		public function onDisconnect(IWebSocketConnection $user){
			echo "[DEMO] {$user->getId()} disconnected\n";
		}

		public function onAdminMessage(IWebSocketConnection $user, IWebSocketMessage $msg){
			echo "[DEMO] Admin Message received!\n";

			$frame = WebSocketFrame::create(WebSocketOpcode::PongFrame);
			$user->sendFrame($frame);
		}

		public function run(){
			$this->server->run();
		}
	}

	// Start server
	$server = new DemoSocketServer();
	$server->run();
```

Client Example
---------------------
```php
      <?php

	$input = "Hello World!";
	$msg = WebSocketMessage::create($input);

	$client = new WebSocket("ws://127.0.0.1:12345/echo/");
	$client->open();
	$client->sendMessage($msg);

	// Wait for an incoming message
	$msg = $client->readMessage();

	$client->close();

	echo $msg->getData(); // Prints "Hello World!" when using the demo.php server
       ?>
```
