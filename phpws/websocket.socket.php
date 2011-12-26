<?php
require_once("websocket.protocol.php");

interface WebSocketObserver{
	public function onDisconnect(WebSocketSocket $s);
	public function onConnectionEstablished(WebSocketSocket $s);
	public function onMessage(IWebSocketConnection $s, IWebSocketMessage $msg);
	public function onFlashXMLRequest(WebSocketConnectionFlash $connection);
}

class WebSocketSocket{
	private $_socket = null;
	private $_protocol = null;

	/**
	 *
	 * @var IWebSocketConnection
	 */
	private $_connection = null;

	private $_writeBuffer = '';

	private $_lastChanged = null;

	private $_disconnecting = false;

	private $_immediateWrite = false;

	const WRITE_BUFFER = 4096;

	/**
	 *
	 * Enter description here ...
	 * @var WebSocketObserver[]
	 */
	private $_observers = array();

	public function __construct(WebSocketObserver $server, $socket, $immediateWrite = false){
		$this->_socket = $socket;
		$this->_lastChanged = time();
		$this->_immediateWrite = $immediateWrite;

		stream_set_blocking($socket, 0);
		//stream_set_read_buffer($socket, 1024*1024);
		//stream_set_write_buffer($socket, 1024*1024);

		$this->addObserver($server);
	}

	public function onData($data){
		try{
			$this->_lastChanged = time();

			if($this->_connection)
				$this->_connection->readFrame($data);
			else $this->establishConnection($data);
		} catch (Exception $e){
			$this->disconnect();
		}
	}

	public function setConnection(IWebSocketConnection $con){
		$this->_connection = $con;
	}

	public function onMessage(IWebSocketMessage $m){
		foreach($this->_observers as $observer){
			$observer->onMessage($this->getConnection(), $m);
		}
	}

	public function establishConnection($data){
		$this->_connection = WebSocketConnectionFactory::fromSocketData($this, $data);

		if($this->_connection instanceof WebSocketConnectionFlash)
			return;

		foreach($this->_observers as $observer){
			$observer->onConnectionEstablished($this);
		}
	}

	public function write($data){
		$this->_writeBuffer .= $data;

		if($this->_immediateWrite == true){
			while($this->_writeBuffer != '')
				$this->mayWrite();
		}
	}

	public function mustWrite(){
		return strlen($this->_writeBuffer);
	}

	public function mayWrite(){

		$write = array($this->_socket);

		@stream_select($read = NULL, $write, $t = NULL, 0);

		if(count($write) == 0)
			return;

		$numBytes = @fwrite($this->_socket, $this->_writeBuffer);

		if($numBytes < strlen($this->_writeBuffer))
			$this->_writeBuffer = substr($this->_writeBuffer, $numBytes);
		else $this->_writeBuffer = '';

		if(strlen($this->_writeBuffer) == 0 && $this->isClosing())
			$this->close();

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
		$this->_disconnecting = true;

		if($this->_writeBuffer == '')
			$this->close();
	}

	public function isClosing(){
		return $this->_disconnecting;
	}

	public function close(){
		fclose($this->_socket);
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