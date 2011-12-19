<?php
require_once("websocket.functions.php");
require_once("websocket.exceptions.php");
require_once("websocket.socket.php");
require_once("websocket.framing.php");
require_once("websocket.message.php");
require_once("websocket.resources.php");


interface IWebSocketServerObserver{
	public function onConnect(IWebSocketConnection $user);
	public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg);
	public function onDisconnect(IWebSocketConnection $user);
	public function onAdminMessage(IWebSocketConnection $user, IWebSocketMessage $msg);
}


/**
 * WebSocketServer
 *
 * @author Chris
 */
class WebSocketServer implements WebSocketObserver{
	protected $master;

	protected $_url;

	/**
	 *
	 * Enter description here ...
	 * @var SplObjectStorage
	 */
	protected $sockets;

	protected $_connections = array();

	/**
	 * @var IWebSocketServerObserver[]
	 */
	protected $_observers = array();

	protected $debug = true;

	protected $purgeUserTimeOut = null;

	protected $_context = null;

	protected $adminKey;

	/**
	 *
	 * Enter description here ...
	 * @var IWebSocketUriHandler[]
	 */
	protected $uriHandlers = array();

	/**
	 * Flash-policy-response for flashplayer/flashplugin
	 * @access protected
	 * @var string
	 */
	protected $FLASH_POLICY_FILE = "<cross-domain-policy><allow-access-from domain=\"*\" to-ports=\"*\" /></cross-domain-policy>\0";


	/**
	 * Handle incoming messages.
	 *
	 * Must be implemented by all extending classes
	 *
	 * @param IWebSocketUser $user The user which sended the message
	 * @param IWebSocketMessage $msg The message that was received (can be WebSocketMessage76 or WebSocketMessage)
	 */

	public function __construct($url, $adminKey){
		$this->adminKey = $adminKey;

		$this->_url = $url;

		$this->sockets = new SplObjectStorage();
		$this->_connections = new SplObjectStorage();

		$this->_context = stream_context_create();

	}

	public function getStreamContext(){
		return $this->_context;
	}

	public function setStreamContext($context){
		$this->_context = $context;
	}

	/**
	 * Start the server
	 */
	public function run(){

		error_reporting(E_ALL);
		set_time_limit(0);

		ob_implicit_flush();


		$err = $errno = 0;

		$port = parse_url($this->_url, PHP_URL_PORT);


		$this->FLASH_POLICY_FILE = str_replace('to-ports="*','to-ports="'.$port,$this->FLASH_POLICY_FILE);

		$this->master = stream_socket_server($this->_url, $err, $errno, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $this->_context);

		$this->say("PHP WebSocket Server");
		$this->say("========================================");
		$this->say("Server Started : ".date('Y-m-d H:i:s'));
		$this->say("Listening on   : ".$this->_url);
		$this->say("========================================");

		$this->sockets->attach(new WebSocket($this, $this->master));


		while(true){

			$this->debug("Blocking on socket_select()");

			// Retreive sockets which are 'Changed'
			$changed = $this->getResources();
			$write = null;
			$except = null;

			stream_select($changed,$write,$except,NULL);

			$this->debug("Socket selected");


			foreach($changed as $resource){

				if($resource==$this->master){
					$this->acceptSocket();
				}else{
					$buffer = '';
					$buffsize = 2048;

					$metadata['unread_bytes'] = 0;

					do{
						$buffer .= fread($resource, $buffsize);
						$metadata = stream_get_meta_data($resource);

						$buffsize = min($buffsize, $metadata['unread_bytes']);

					} while($metadata['unread_bytes'] > 0);

					$bytes = strlen($buffer);

					$socket = $this->getSocketByResource($resource);

					if($bytes === false){
						$socket->disconnect();
					}else if($bytes === 0) {
						$socket->disconnect();
					} else if($socket != null){
						$socket->onData($buffer);
					}
				}
			}

			$this->debug('Number of users connected: '.count($this->getConnections()));
			$this->purgeUsers();
		}
	}

	private function acceptSocket(){
		try{
			$client=stream_socket_accept($this->master);
			if($client === false){
				WebSocketFunctions::say('socket_accept() failed');
			}

			$this->sockets->attach(new WebSocket($this, $client));

			$this->debug("Socket accepted");

		} catch(Exception $e){
			$this->say($e);
		}
	}

	private function getSocketByResource($res){
		foreach($this->sockets as $socket){
			if($socket->getResource() == $res)
				return $socket;
		}
	}

	private function getResources(){
		$resources = array();

		foreach($this->sockets as $socket){
			$resources[] = $socket->getResource();
		}

		return $resources;
	}


	/**
	 * Dispatch an admin message to the associated resource handler or to the servers prefixed onAdmin functions

	 * @param WebSocketAdminUser $user
	 * @param stdClass $obj
	 */
	protected function dispatchAdminMessage(IWebSocketConnection $user, IWebSocketMessage $msg){

		if(array_key_exists($this->_connections[$user],$this->uriHandlers)){
			$this->uriHandlers[$this->_connections[$user]]->onAdminMessage($user, $msg);
		}

		foreach($this->_observers as $o){
			$o->onAdminMessage($user, $msg);
		}

	}

	public function addObserver(IWebSocketServerObserver $o){
		$this->_observers[] = $o;
	}



	/**
	 * Associate a request uri to a IWebSocketResourceHandler.
	 *
	 * @param string $script For example 'handler1' to capture request with URI '/handler1/'
	 * @param IWebSocketResourceHandler $handler Instance of a IWebSocketResourceHandler. This instance will receive the messages.
	 */
	public function addUriHandler($script, IWebSocketUriHandler $handler){
		$this->uriHandlers[$script] = $handler;
		$handler->setServer($this);
	}




	/**
	 * Dispatch incoming message to the associated resource and to the general onMessage event handler

	 * @param IWebSocketUser $user
	 * @param IWebSocketMessage $msg
	 */
	protected function dispatchMessage(IWebSocketConnection $user,IWebSocketMessage $msg){
		$this->debug("dispatchMessage");

		if(array_key_exists($this->_connections[$user],$this->uriHandlers)){
			$this->uriHandlers[$this->_connections[$user]]->onMessage($user, $msg);
		}

		foreach($this->_observers as $o){
			$o->onMessage($user, $msg);
		}
	}




	/**
	 * Adds a user to a IWebSocketResourceHandler by using the request uri in the GET request of
	 * the client's opening handshake
	 *
	 * @param IWebSocketUser $user
	 * @param array $headers
	 * @return IWebSocketResourceHandler Instance of the resource handler the user has been added to.
	 */
	protected function addConnectionToUriHandler(WebSocketConnection $user, $uri){
		$url = parse_url($uri);

		if(isset($url['query']))
			parse_str($url['query'], $query);
		else $query = array();

		if(isset($url['path']) == false)
			$url['path'] = '/';

		$resource = array_pop(preg_split("/\//",$url['path'],0,PREG_SPLIT_NO_EMPTY));
		$user->parameters = $query;


		if(array_key_exists($resource, $this->uriHandlers)){
			$this->uriHandlers[$resource]->addConnection($user);
			$this->_connections[$user] = $resource;

			$this->say("User has been added to $resource");
		}
	}


	/**
	 * Find the user associated with the socket
	 *
	 * @param socket $socket
	 * @return IWebSocketUser User associated with the socket, returns null when none found
	 */
	protected function getUserBySocket($socket){
		$found=null;
		foreach($this->users as $user){
			if($user->getSocket()==$socket){ $found=$user; break; }
		}
		return $found;
	}

	/**
	 * Output a line to stdout
	 *
	 * @param string $msg Message to output to the STDOUT
	 */
	public function say($msg = ""){
		echo date("Y-m-d H:i:s")." | ".$msg."\n";
	}

	public function onConnectionEstablished(WebSocket $s){
		$con = $s->getConnection();
		$this->_connections->attach($con);

		$uri = $con->getUriRequested();

		$this->addConnectionToUriHandler($con, $uri);

		foreach($this->_observers as $o){
			/**
			 * @var @o IWebSocketServerObserver
			 */

			$o->onConnect($con);

		}

	}

	public function onMessage(IWebSocketConnection $connection, IWebSocketMessage $msg){
		try{
			if($connection->getAdminKey() == $this->getAdminKey())
				$this->dispatchAdminMessage($connection, $msg);
			else $this->dispatchMessage($connection, $msg);
		} catch (Exception $e){
			$this->say("Exception occurred while handling message:\r\n".$e->getTraceAsString());
		}
	}

	public function onDisconnect(WebSocket $socket){
		$con = $socket->getConnection();
		try{
			if($con){
				$handler = $this->_connections[$con];

				if($handler)
					$this->uriHandlers[$handler]->removeConnection($con);

				$this->_connections->detach($socket->getConnection());
			}
		} catch (Exception $e){
			$this->say("Exception occurred while handling message:\r\n".$e->getTraceAsString());
		}


		if($con){
			foreach($this->_observers as $o){
				/**
				 * @var @o IWebSocketServerObserver
				 */

				$o->onDisconnect($con);

			}
		}

		$this->sockets->detach($socket);
	}


	protected function purgeUsers(){
		$currentTime = time();

		if($this->purgeUserTimeOut == NULL)
			return;

		foreach($this->_sockets as $s){
			if($currentTime - $u->getLastChanged() > $this->purgeUserTimeOut){
				$u->disconnect();
				$this->onDisconnect($u);
			}
		}
	}

	public function getConnections(){
		return $this->_connections;
	}

	public function debug($msg){
		if($this->debug)
			echo date("Y-m-d H:i:s")." | ".$msg."\n";
	}

	public function onFlashXMLRequest(WebSocketConnectionFlash $connection){
		$connection->sendString($this->FLASH_POLICY_FILE);
		$connection->disconnect();
	}

	protected function getAdminKey(){
		return $this->adminKey;
	}

	public function isAdmin(WebSocketConnection $con){
		return $this->getAdminKey() === $con->getAdminKey();
	}

	/**
	 *
	 * @param IWebSocketUriHandler $uri
	 */
	public function getUriHandler($uri){
		return $this->uriHandlers[$uri];
	}
}


