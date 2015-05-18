<?php

namespace Devristo\Phpws\Server;

use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketOpcode;
use Devristo\Phpws\Protocol\Handshake;
use Devristo\Phpws\Protocol\WebSocketTransportHybi;
use Devristo\Phpws\Protocol\WebSocketTransportInterface;
use Devristo\Phpws\Protocol\WebSocketConnection;
use Evenement\EventEmitter;
use Exception;
use React\EventLoop\LoopInterface;
use SplObjectStorage;
use Zend\Log\LoggerInterface;
use Zend\Uri\Uri;

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
     * @var WebSocketConnection[]|SplObjectStorage
     */
    protected $_streams;

    /**
     * The connected clients to the WebSocket server, a valid handshake has been performed.
     * @var \SplObjectStorage|WebSocketTransportInterface[]
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
     * @param \Zend\Log\LoggerInterface $logger
     * @throws \InvalidArgumentException
     */
    public function __construct($url, LoopInterface $loop, LoggerInterface $logger)
    {
        $uri = new Uri($url);

        if($uri->getScheme() == 'ws')
            $uri->setScheme('tcp');
        elseif($uri->getScheme() == 'wss')
            $uri->setScheme('ssl');

        if($uri->getScheme() != 'tcp' && $uri->getScheme() != 'ssl')
            throw new \InvalidArgumentException("Uri scheme must be one of: tcp, ssl, ws, wss");

        $this->uri = $uri;

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

        $this->FLASH_POLICY_FILE = str_replace('to-ports="*', 'to-ports="' . $this->uri->getPort() ?: 80, $this->FLASH_POLICY_FILE);

        $serverSocket = stream_socket_server($this->uri->toString(), $errno, $err, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $this->_context);

        $this->_logger->notice(sprintf("phpws listening on %s", $this->uri->toString()));

        if ($serverSocket == false) {
            $this->_logger->err("Error: $err");
            return;
        }

        $timeOut = & $this->purgeUserTimeOut;
        $sockets = $this->_streams;
        $that = $this;
        $logger = $this->_logger;

        $this->loop->addReadStream($serverSocket, function ($serverSocket) use ($that, $logger, $sockets) {
            try
            {
                $newSocket = stream_socket_accept($serverSocket);
            } catch (\ErrorException $e) {
                $newSocket = false;
            }

            if (false === $newSocket) {
                return;
            }

            stream_set_blocking($newSocket, 0);
            $client = new WebSocketConnection($newSocket, $that->loop, $logger);
            $sockets->attach($client);

            $client->on("handshake", function(Handshake $request) use($that, $client){
                $that->emit("handshake",array($client->getTransport(), $request));
            });

            $client->on("connect", function () use ($that, $client, $logger) {
                $con = $client->getTransport();
                $that->getConnections()->attach($con);
                $that->emit("connect", array("client" => $con));
            });

            $client->on("message", function ($message) use ($that, $client, $logger) {
                $connection = $client->getTransport();
                $that->emit("message", array("client" => $connection, "message" => $message));
            });

            $client->on("close", function () use ($that, $client, $logger, &$sockets, $client) {
                $sockets->detach($client);
                $connection = $client->getTransport();

                if($connection){
                    $that->getConnections()->detach($connection);
                    $that->emit("disconnect", array("client" => $connection));
                }
            });

            $client->on("flashXmlRequest", function () use ($that, $client) {
                $client->getTransport()->sendString($that->FLASH_POLICY_FILE);
                $client->close();
            });
        });

        $this->loop->addPeriodicTimer(5, function () use ($timeOut, $sockets, $that) {

            # Lets send some pings
            foreach($that->getConnections() as $c){
                if($c instanceof WebSocketTransportHybi)
                    $c->sendFrame(WebSocketFrame::create(WebSocketOpcode::PingFrame));
            }

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

