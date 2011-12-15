<?php
require_once("websocket.functions.php");
require_once("websocket.exceptions.php");
require_once("websocket.framing.php");
require_once("websocket.message.php");
require_once("websocket.resources.php");

class WebSocket{
	protected $socket;
	protected $handshakeChallenge;
	protected $hixieKey1;
	protected $hixieKey2;
	protected $host;
	protected $port;
	protected $origin;
	protected $requestUri;
	protected $url;

	protected $hybi;

	// mamta
	public function __construct($url, $useHybie = true){
		$this->hybi = $useHybie;
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

		// mamta
		if ($useHybie) {
			$this->buildHeaderArray();
		} else {
			$this->buildHeaderArrayHixie76();
		}
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
		# mamta add key 3 needed for the handshake/swithching protocol compatible with glassfish
		$key3 = WebSocketFunctions::genKey3();
		$str .= "\r\n".$key3;

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

	# mamta: hixie 76
	protected function buildHeaderArrayHixie76(){
		$this->hixieKey1 = WebSocketFunctions::randHixieKey();
		$this->hixieKey2 = WebSocketFunctions::randHixieKey();
		$this->headers = array(
			"GET" => "{$this->url} HTTP/1.1",
			"Connection:" => "Upgrade",
			"Host:" => "{$this->host}:{$this->port}",
			"Origin:" => "{$this->origin}",
			"Sec-WebSocket-Key1:" => "{$this->hixieKey1->key}",
			"Sec-WebSocket-Key2:" => "{$this->hixieKey2->key}",
			"Upgrade:" => "websocket",
			"Sec-WebSocket-Protocol: " => "hiwavenet"
			);

		return $this->headers;
	}

	public function send($string){

		if($this->hybi)
			$msg = WebSocketMessage::create($string);
		else $msg = WebSocketMessage76::create($string);

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

		if($this->hybi)
			return WebSocketFrame::decode($data);
		else return WebSocketFrame76::decode($data);
	}

	public function readMessage(){
		$frame = $this->readFrame();

		if($frame == null)
			return null;

		if($this->hybi)
			$msg = WebSocketMessage::fromFrame($frame);
		else $msg = WebSocketMessage76::fromFrame($frame);


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