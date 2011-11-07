<?php
require_once("websocket.protocol.php");

interface WebSocketObserver{
	public function onDisconnect(WebSocket $s);
	public function onConnectionEstablished(WebSocket $s);
	public function onMessage(IWebSocketConnection $s, IWebSocketMessage $msg);
	public function onFlashXMLRequest(WebSocketConnectionFlash $connection);
}

class WebSocket{
	private $_socket = null;
	private $_protocol = null;
	private $_connection = null;

	private $_lastChanged = null;

	/**
	 *
	 * Enter description here ...
	 * @var WebSocketObserver[]
	 */
	private $_observers = array();

	public function __construct(WebSocketObserver $server, $socket){
		$this->_socket = $socket;
		$this->_lastChanged = time();

		$this->addObserver($server);
	}

	public function onData($data){
		$this->_lastChanged = time();

		if($this->_connection)
			$this->_connection->readFrame($data);
		else $this->establishConnection($data);
	}

	public function onMessage(IWebSocketMessage $m){
		WebSocketFunctions::say("MESSAGE RECEIVED {$m->getData()}");

		foreach($this->_observers as $observer){
			$observer->onMessage($this->getConnection(), $m);
		}
	}

	public function establishConnection($data){
		$this->_connection = WebSocketConnectionFactory::fromSocketData($this, $data);

		foreach($this->_observers as $observer){
			$observer->onConnectionEstablished($this);
		}
	}

	public function write($data){
		if(@socket_write($this->_socket, $data,strlen($data)) === false)
			$this->disconnect();
	}

	public function getLastChanged(){
		return $this->_lastChanged;
	}

	public function onFlashXMLRequest(WebSocketConnectionFlash $connection){
		foreach($this->_observers as $observer){
			$observer->onFlashXMLRequest($connection);
		}
	}

	public function disconnect(){
		socket_close($this->_socket);

		foreach($this->_observers as $observer){
			$observer->onDisconnect($this);
		}
	}

	public function getResource(){
		return $this->_socket;
	}

	/**
	 *
	 * @return IWebSocketConnection
	 */
	public function getConnection(){
		return $this->_connection;
	}

	public function addObserver(WebSocketObserver $s){
		$this->_observers[] = $s;
	}

}