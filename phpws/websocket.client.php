<?php
require_once("websocket.functions.php");
require_once("websocket.exceptions.php");
require_once("websocket.framing.php");
require_once("websocket.message.php");
require_once("websocket.resources.php");

class WebSocket{
	protected $socket;
	protected $handshakeChallenge;
	protected $host;
	protected $port;
	protected $origin;
	protected $requestUri;
	protected $url;

	public function __construct($url){
		$parts = parse_url($url);

		$this->url = $url;

		if(in_array($parts['scheme'], array('ws','wss')) === false)
			throw new WebSocketInvalidUrlScheme();

		$this->scheme = $parts['scheme'];

		$this->host = $parts['host'];
		$this->port = $parts['port'];

		$this->origin = 'http://'.$this->host;

		if(isset($parts['path']))
			$this->requestUri = $parts['path'];
		else $this->requestUri = "/";

		if(isset($parts['query']))
			$this->requestUri .= "?".$parts['query'];

		$this->buildHeaderArray();
	}

	public function setTimeOut($seconds){
		$this->_timeOut = $seconds;
	}

	public function getTimeOut(){
		return $this->_timeOut;
	}

	/**
	 * TODO: Proper header generation!
	 * TODO: Check server response!
	 */
	public function open(){
		$errno = $errstr = null;

		$protocol = $this->scheme == 'ws' ? "tcp" : "ssl";

		$this->socket = stream_socket_client("$protocol://{$this->host}:{$this->port}", $errno, $errstr, $this->getTimeOut());// socket_connect($this->socket, $this->host, $this->port);

		$buffer = $this->serializeHeaders();

		fwrite($this->socket, $buffer, strlen($buffer));

		// wait for response
		$buffer = fread($this->socket, 2048);
		$headers = WebSocketFunctions::parseHeaders($buffer);

		if($headers['Sec-Websocket-Accept'] != WebSocketFunctions::calcHybiResponse($this->handshakeChallenge)){
			return false;
		}

		return true;
	}

	private function serializeHeaders(){
		$str = '';

		foreach($this->headers as $k => $v){
			$str .= $k." ".$v."\r\n";
		}

		return $str;
	}

	public function addHeader($key, $value){
		$this->headers[$key.":"] = $value;
	}

	protected function buildHeaderArray(){
		$this->handshakeChallenge = WebSocketFunctions::randHybiKey();

		$this->headers = array(
			"GET" => "{$this->url} HTTP/1.1",
			"Connection:" => "Upgrade",
			"Host:" => "{$this->host}:{$this->port}",
			"Sec-WebSocket-Key:" => "{$this->handshakeChallenge}",
			"Sec-WebSocket-Origin:" => "{$this->origin}",
			"Sec-WebSocket-Version:" => 8,
			"Upgrade:" => "websocket"
		);

		return $headers;
	}

	public function send($string){
		$msg = WebSocketMessage::create($string);

		$this->sendMessage($msg);
	}

	public function sendMessage(IWebSocketMessage $msg){
		// Sent all fragments
		foreach($msg->getFrames() as $frame){
			$this->sendFrame($frame);
		}
	}

	public function sendFrame(IWebSocketFrame $frame){
		$msg = $frame->encode();
		fwrite($this->socket, $msg,strlen($msg));
	}

	/**
	 * @return WebSocketFrame
	 */
	public function readFrame(){
		$data = fread($this->socket,2048);

		if($data === false)
			return null;

		return WebSocketFrame::decode($data);
	}

	public function readMessage(){
		$frame = $this->readFrame();

		if($frame != null)
			$msg = WebSocketMessage::fromFrame($frame);
		else return null;

		while($msg->isFinalised() == false){
			$frame = $this->readFrame();

			if($frame != null)
				$msg->takeFrame($this->readFrame());
			else return null;
		}

		return $msg;
	}

	public function close(){
		/**
		 * @var WebSocketFrame
		 */
		$frame = null;
		$this->sendFrame(WebSocketFrame::create(WebSocketOpcode::CloseFrame));

		$i = 0;
		do{
			$i++;
			$frame =  @$this->readFrame();
		}while($i < 2 && $frame && $frame->getType == WebSocketOpcode::CloseFrame);


		fclose($this->socket);
	}

}