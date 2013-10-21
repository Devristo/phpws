<?php

namespace Devristo\Phpws\Server;
use Devristo\Phpws\Messaging\IWebSocketMessage;
use Devristo\Phpws\Protocol\IWebSocketConnection;
use Devristo\Phpws\Protocol\WebSocketConnection;
use Devristo\Phpws\Protocol\WebSocketConnectionFlash;
use Devristo\Phpws\Protocol\WebSocketObserver;
use Devristo\Phpws\Protocol\WebSocketStream;
use Devristo\Phpws\Server\UriHandler\IWebSocketUriHandler;
use Exception;
use SplObjectStorage;

/**
 * WebSocketServer
 *
 * @author Chris
 */
class WebSocketServer implements WebSocketObserver
{

    protected $master;
    protected $_url;

    /**
     *
     * Enter description here ...
     * @var WebSocketStream[]|SplObjectStorage
     */
    protected $sockets;

    /**
     * @var \SplObjectStorage|IWebSocketConnection[]
     */
    protected $_connections = array();

    /**
     * @var \Devristo\Phpws\Server\IWebSocketServerObserver[]
     */
    protected $_observers = array();
    protected $debug = true;
    protected $purgeUserTimeOut = null;
    protected $_context = null;

    /**
     *
     * Enter description here ...
     * @var \Devristo\Phpws\Server\UriHandler\IWebSocketUriHandler[]
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
     * @param $url
     * @param bool $showHeaders
     */
    public function __construct($url, $showHeaders = false)
    {
        define("WS_DEBUG_HEADER", $showHeaders);


        $this->_url = $url;

        $this->sockets = new SplObjectStorage();
        $this->_connections = new SplObjectStorage();

        $this->_context = stream_context_create();
    }

    public function getStreamContext()
    {
        return $this->_context;
    }

    public function setStreamContext($context)
    {
        $this->_context = $context;
    }

    /**
     * Unassociate a request uri to a IWebSocketResourceHandler.
     *
     * @param string $script For example 'handler1' to capture request with URI '/handler1/'
     * @param bool $disconnectUsers if true, disconnect users assosiated to handler.
     * @return bool|\Devristo\Phpws\Server\UriHandler\IWebSocketUriHandler
     */
    public function removeUriHandler($script, $disconnectUsers = true)
    {

        if (empty($this->uriHandlers[$script]))
            return false;
        $handler = $this->uriHandlers[$script];
        unset($this->uriHandlers[$script]);

        if ($disconnectUsers)
            foreach ($handler->getConnections() as $user) {

                $handler->removeConnection($user);
                $user->disconnect();
            }

        return $handler;
    }

    /**
     * Start the server
     */
    public function run()
    {

        error_reporting(E_ALL);
        set_time_limit(0);

        ob_implicit_flush();


        $err = $errno = 0;

        $port = parse_url($this->_url, PHP_URL_PORT);


        $this->FLASH_POLICY_FILE = str_replace('to-ports="*', 'to-ports="' . $port, $this->FLASH_POLICY_FILE);

        $this->master = stream_socket_server($this->_url, $errno, $err, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $this->_context);

        $this->say("PHP WebSocket Server");
        $this->say("========================================");
        $this->say("Server Started : " . date('Y-m-d H:i:s'));
        $this->say("Listening on   : " . $this->_url);
        $this->say("========================================");

        if ($this->master == false) {
            $this->say("Error: $err");
            return;
        }

        $this->sockets->attach(new WebSocketStream($this, $this->master));


        while (true) {

            clearstatcache();
            // Garbage Collection (PHP >= 5.3)
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            //$this->debug("Blocking on socket_select()");
            // Retreive sockets which are 'Changed'
            $changed = $this->getResources();
            $write = $this->getWriteStreams();
            $except = null;

            if (@stream_select($changed, $write, $except, null) === false) {
                $this->say("Select failed!");
                break;
            }


            //$this->debug("Socket selected");


            foreach ($changed as $resource) {
                if ($resource == $this->master) {
                    $this->acceptSocket();
                } else {
                    $buffer = fread($resource, 8192);

                    $socket = $this->getSocketByResource($resource);

                    // If read returns false, close the stream and continue with the next socket
                    if ($buffer === false) {
                        $socket->close();
                        // Skip to next stream
                        continue;
                    }

                    $bytes = strlen($buffer);

                    if ($bytes === 0) {
                        $socket->close();
                    } else if ($socket != null) {
                        $socket->onData($buffer);
                    }
                }
            }

            if (is_array($write)) {
                foreach ($write as $s) {
                    $o = $this->getSocketByResource($s);
                    if ($o != null)
                        $o->mayWrite();
                }
            }


            //$this->debug('Number of users connected: '.count($this->getConnections()));
            $this->purgeUsers();
        }
    }

    private function acceptSocket()
    {
        try {
            $client = stream_socket_accept($this->master);
            stream_set_blocking($client, 0);

            if ($client === false) {
                echo 'socket_accept() failed\n';
            }

            $this->sockets->attach(new WebSocketStream($this, $client));

            $this->debug("Socket accepted");
        } catch (Exception $e) {
            $this->say($e);
        }
    }

    /**
     *
     * @param resource $res
     * @return \Devristo\Phpws\Protocol\WebSocketStream
     */
    private function getSocketByResource($res)
    {
        foreach ($this->sockets as $socket) {
            if ($socket->getResource() == $res)
                return $socket;
        }

        return null;
    }

    private function getResources()
    {
        $resources = array();

        foreach ($this->sockets as $socket) {
            $resources[] = $socket->getResource();
        }

        return $resources;
    }

    public function addObserver(IWebSocketServerObserver $o)
    {
        $this->_observers[] = $o;
    }

    /**
     * Associate a request uri to a IWebSocketResourceHandler.
     *
     * @param string $script For example 'handler1' to capture request with URI '/handler1/'
     * @param \Devristo\Phpws\Server\UriHandler\IWebSocketUriHandler $handler Instance of a IWebSocketResourceHandler. This instance will receive the messages.
     */
    public function addUriHandler($script, IWebSocketUriHandler $handler)
    {
        $this->uriHandlers[$script] = $handler;
        $handler->setServer($this);
    }

    /**
     * Dispatch incoming message to the associated resource and to the general onMessage event handler
     * @param \Devristo\Phpws\Protocol\IWebSocketConnection $user
     * @param IWebSocketMessage $msg
     */
    protected function dispatchMessage(IWebSocketConnection $user, IWebSocketMessage $msg)
    {
        $this->debug("dispatchMessage");

        if (array_key_exists($this->_connections[$user], $this->uriHandlers)) {
            $this->uriHandlers[$this->_connections[$user]]->onMessage($user, $msg);
        }

        foreach ($this->_observers as $o) {
            $o->onMessage($user, $msg);
        }
    }

    /**
     * Adds a user to a IWebSocketResourceHandler by using the request uri in the GET request of
     * the client's opening handshake
     *
     * @param \Devristo\Phpws\Protocol\IWebSocketConnection $user
     * @param $uri
     * @return IWebSocketUriHandler Instance of the resource handler the user has been added to.
     */
    protected function addConnectionToUriHandler(IWebSocketConnection $user, $uri)
    {
        $url = parse_url($uri);

        if (isset($url['query']))
            parse_str($url['query'], $query);
        else
            $query = array();

        if (isset($url['path']) == false)
            $url['path'] = '/';

        $pathSplit = preg_split("/\//", $url['path'], 0, PREG_SPLIT_NO_EMPTY);
        $resource = array_pop($pathSplit);

        $user->parameters = $query;


        if (array_key_exists($resource, $this->uriHandlers)) {
            $this->uriHandlers[$resource]->addConnection($user);
            $this->_connections[$user] = $resource;

            $this->say("User has been added to $resource");
        }
    }

    /**
     * Output a line to stdout
     *
     * @param string $msg Message to output to the STDOUT
     */
    public function say($msg = "")
    {
        echo date("Y-m-d H:i:s") . " | " . $msg . "\n";
    }

    public function onConnectionEstablished(WebSocketStream $s)
    {
        $con = $s->getConnection();
        $this->_connections->attach($con);

        $uri = $con->getUriRequested();

        $this->addConnectionToUriHandler($con, $uri);

        foreach ($this->_observers as $o) {
            /**
             * @var @o IWebSocketServerObserver
             */
            $o->onConnect($con);
        }
    }

    public function onMessage(IWebSocketConnection $connection, IWebSocketMessage $msg)
    {
        try {
            $this->dispatchMessage($connection, $msg);
        } catch (Exception $e) {
            $this->say("Exception occurred while handling message:\r\n" . $e->getTraceAsString());
        }
    }

    public function onDisconnect(WebSocketStream $socket)
    {
        $con = $socket->getConnection();
        try {
            if ($con) {
                $handler = $this->_connections[$con];

                if ($handler)
                    $this->uriHandlers[$handler]->removeConnection($con);

                $this->_connections->detach($socket->getConnection());
            }
        } catch (Exception $e) {
            $this->say("Exception occurred while handling message:\r\n" . $e->getTraceAsString());
        }


        if ($con instanceof IWebSocketConnection) {
            foreach ($this->_observers as $o) {
                /**
                 * @var @o IWebSocketServerObserver
                 */
                $o->onDisconnect($con);
            }
        }

        $this->sockets->detach($socket);
    }

    protected function purgeUsers()
    {
        $currentTime = time();

        if ($this->purgeUserTimeOut == null)
            return;

        foreach ($this->sockets as $s) {
            if ($currentTime - $s->getLastChanged() > $this->purgeUserTimeOut) {
                $s->disconnect();
                $this->onDisconnect($s);
            }
        }
    }

    public function getConnections()
    {
        return $this->_connections;
    }

    public function debug($msg)
    {
        if ($this->debug)
            echo date("Y-m-d H:i:s") . " | " . $msg . "\n";
    }

    public function onFlashXMLRequest(WebSocketConnectionFlash $connection)
    {
        $connection->sendString($this->FLASH_POLICY_FILE);
        $connection->disconnect();
    }

    protected function getWriteStreams()
    {
        $resources = array();

        foreach ($this->sockets as $socket) {
            if ($socket->mustWrite())
                $resources[] = $socket->getResource();
        }

        return $resources;
    }


    /**
     *
     * @param \Devristo\Phpws\Server\UriHandler\IWebSocketUriHandler $uri
     *
     * @return \Devristo\Phpws\Server\UriHandler\IWebSocketUriHandler
     */
    public function getUriHandler($uri)
    {
        return $this->uriHandlers[$uri];
    }

}

