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
use Zend\Log\LoggerInterface;

/**
 * WebSocketServer
 *
 * @author Chris
 */
class WebSocketServer implements WebSocketObserver, ISocketStream
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
     * @param null $logger
     */
    public function __construct($url, LoggerInterface $logger)
    {
        $this->_url = $url;

        $this->_connections = new SplObjectStorage();

        $this->_context = stream_context_create();
        $this->_logger = $logger;
        $this->_server = new SocketServer($logger);
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

        $this->_logger->notice(sprintf("phpws listening on %s", $this->_url));

        if ($this->master == false) {
            $this->_logger->err("Error: $err");
            return;
        }

        $this->_server->attachStream($this);
        $this->_server->run();
    }

    public function acceptConnection()
    {
        try {
            $client = stream_socket_accept($this->master);
            stream_set_blocking($client, 0);

            if ($client === false) {
                $this->_logger->warn("Failed to accept client connection");
            }
            $stream = new WebSocketStream($this, $client);
            $stream->setLogger($this->_logger);
            $this->_server->attachStream($stream);

            $this->_logger->info("WebSocket client accepted");
        } catch (Exception $e) {
            $this->_logger->crit("Failed to accept client connection");
        }
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
        $this->_logger->debug("Dispatching message to URI handlers and Observers");

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

        $pathSplit = preg_split("/\\//", $url['path'], 0, PREG_SPLIT_NO_EMPTY);
        $resource = array_pop($pathSplit);

        $user->parameters = $query;


        if (array_key_exists($resource, $this->uriHandlers)) {
            $this->uriHandlers[$resource]->addConnection($user);
            $this->_connections[$user] = $resource;

            $this->_logger->notice("User {$user->getId()} has been added to $resource");
        }
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
            $this->_logger->error("Exception occurred while handling message:\r\n" . $e->getTraceAsString());
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
            $this->_logger->err("Exception occurred while handling message:\r\n" . $e->getTraceAsString());
        }


        if ($con instanceof IWebSocketConnection) {
            foreach ($this->_observers as $o) {
                /**
                 * @var @o IWebSocketServerObserver
                 */
                $o->onDisconnect($con);
            }
        }

        $this->_server->detachStream($socket);
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

    public function onData($data)
    {
        throw new \BadMethodCallException();
    }

    public function close()
    {
        fclose($this->getSocket());
    }

    public function mayWrite()
    {
        return;
    }

    public function requestsWrite()
    {
        return false;
    }

    public function getSocket()
    {
        return $this->master;
    }

    public function isServer()
    {
        return true;
    }

    public function isClosed(){
        return false;
    }
}

