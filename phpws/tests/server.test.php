<?php
require_once("../websocket.client.php");
require_once("../websocket.admin.php");
require_once('simpletest/autorun.php');

/**
 * These tests need the 'demo.php' server to be running
 *
 * @author Chris
 *
 */
class test extends UnitTestCase {
	function test_echoResourceHandlerResponse(){
		$input = "Hello World!";
		$msg = WebSocketMessage::create($input);

		$client = new WebSocket("ws://127.0.0.1:12345/echo/");
		$client->open();
		$client->sendMessage($msg);

		$msg = $client->readMessage();

		$client->close();
		$this->assertEqual($input, $msg->getData());
	}

	function test_DoubleEchoResourceHandlerResponse(){
		$input = str_repeat("a", 1024*1024);
		$input2 = str_repeat("b", 1024*1024);
		$msg = WebSocketMessage::create($input);

		$client = new WebSocket("ws://127.0.0.1:12345/echo/");
		$client->setTimeOut(1000);
		$client->open();
		$client->sendMessage($msg);
		$client->sendMessage(WebSocketMessage::create($input2));

		$msg = $client->readMessage();
		$msg2= $client->readMessage();

		$client->close();
		$this->assertEqual($input, $msg->getData());

		$this->assertEqual($input2, $msg2->getData());
	}
}