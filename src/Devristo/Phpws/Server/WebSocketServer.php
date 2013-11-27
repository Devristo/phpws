<?php

namespace Devristo\Phpws\Server;

use Devristo\Phpws\Protocol\WebSocketConnectionInterface;
use Devristo\Phpws\Protocol\WebSocketServerClient;
use Evenement\EventEmitter;
use Exception;
use React\EventLoop\LoopInterface;
use SplObjectStorage;
use Zend\Log\LoggerInterface;

/**
 * WebSocketServer
 *
 * @author Chris
 */
class WebSocketServer extends EventEmitter
{
    protected $_url;

    /**
     *
     * The raw streams connected to the WebSocket server (whether a handshake has taken place or not)
     * @var WebSocketServerClient[]|SplObjectStorage
     */
    protected $_streams;

    /**
     * The connected clients to the WebSocket server, a valid handshake has been performed.
     * @var \SplObjectStorage|WebSocketConnectionInterface[]
     */
    protected $_connections = array();

    protected $purgeUserTimeOut = null;
    protected $_context = null;

    /**
     *
     * Enter description here ...
     * @var \Devristo\Phpws\Server\UriHandler\WebSocketUriHandlerInterface[]
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
     * @param \React\EventLoop\LoopInterface $loop
     * @param null|\Zend\Log\LoggerInterface $logger
     */
    public function __construct($url, LoopInterface $loop, LoggerInterface $logger)
    {
        $this->_url = $url;

        $this->loop = $loop;
        $this->_streams = new SplObjectStorage();
        $this->_connections = new SplObjectStorage();

        $this->_context = stream_context_create();
        $this->_logger = $logger;
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
     * Start the server
     */
    public function bind()
    {

        $err = $errno = 0;

        $port = parse_url($this->_url, PHP_URL_PORT);
        $this->FLASH_POLICY_FILE = str_replace('to-ports="*', 'to-ports="' . $port, $this->FLASH_POLICY_FILE);

        $serverSocket = stream_socket_server($this->_url, $errno, $err, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $this->_context);

        $this->_logger->notice(sprintf("phpws listening on %s", $this->_url));

        if ($serverSocket == false) {
            $this->_logger->err("Error: $err");
            return;
        }

        $timeOut = & $this->purgeUserTimeOut;
        $sockets = $this->_streams;
        $that = $this;
        $logger = $this->_logger;

        $this->loop->addReadStream($serverSocket, function ($serverSocket) use ($that, $logger, $sockets) {
            $newSocket = stream_socket_accept($serverSocket);

            if (false === $newSocket) {
                return;
            }

            stream_set_blocking($newSocket, 0);
            $client = new WebSocketServerClient($newSocket, $that->loop, $logger);
            $sockets->attach($client);

            $client->on("connect", function () use ($that, $client, $logger) {
                try {
                    $con = $client->getConnection();
                    $that->getConnections()->attach($con);
                    $that->emit("connect", array("client" => $con));
                } catch (Exception $e) {
                    $logger->err("[on_connect] Error occurred while running a callback");
                }
            });

            $client->on("message", function ($message) use ($that, $client, $logger) {
                try {
                    $connection = $client->getConnection();
                    $that->emit("message", array("client" => $connection, "message" => $message));
                } catch (Exception $e) {
                    $logger->err("[on_message] Error occurred while running a callback");
                }
            });

            $client->on("close", function () use ($that, $client, $logger) {
                try{
                    $connection = $client->getConnection();

                    if($connection){
                        $that->getConnections()->detach($connection);
                        $that->emit("disconnect", array("client" => $connection));
                    }
                }catch (Exception $e) {
                    $logger->err("[on_message] Error occurred while running a callback");
                }
            });

            $client->on("flashXmlRequest", function () use ($that, $client) {
                $client->getConnection()->sendString($that->FLASH_POLICY_FILE);
                $client->close();
            });
        });

        $this->loop->addPeriodicTimer(5, function () use ($timeOut, $sockets, $that) {
            $currentTime = time();

            if ($timeOut == null)
                return;

            foreach ($sockets as $s) {
                if ($currentTime - $s->getLastChanged() > $timeOut) {
                    $s->close();
                }
            }
        });
    }

    public function getConnections()
    {
        return $this->_connections;
    }
}

