<?php

namespace Devristo\Phpws\Server;

use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketOpcode;
use Devristo\Phpws\Protocol\Handshake;
use Devristo\Phpws\Protocol\WebSocketTransportHybi;
use Devristo\Phpws\Protocol\WebSocketTransportInterface;
use Devristo\Phpws\Protocol\WebSocketConnection;
use Evenement\EventEmitter;
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
    protected $url;

    /**
     * @var Uri
     */
    protected $uri;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     *
     * The raw streams connected to the WebSocket server (whether a handshake has taken place or not)
     * @var WebSocketConnection[]|SplObjectStorage
     */
    protected $streams;

    /**
     * The connected clients to the WebSocket server, a valid handshake has been performed.
     * @var \SplObjectStorage|WebSocketTransportInterface[]
     */
    protected $connections = [];

    protected $purgeUserTimeOut;
    protected $context;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     *
     * Enter description here ...
     * @var \Devristo\Phpws\Server\UriHandler\WebSocketUriHandlerInterface[]
     */
    protected $uriHandlers = [];

    /**
     * Flash-policy-response for flashplayer/flashplugin
     * @access protected
     * @var string
     */
    protected $flashPolicyFile =
        "<cross-domain-policy><allow-access-from domain=\"*\" to-ports=\"*\" /></cross-domain-policy>\0";

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

        if ($uri->getScheme() == 'ws') {
            $uri->setScheme('tcp');
        } elseif ($uri->getScheme() == 'wss') {
            $uri->setScheme('ssl');
        }

        if ($uri->getScheme() != 'tcp' && $uri->getScheme() != 'ssl') {
            throw new \InvalidArgumentException("Uri scheme must be one of: tcp, ssl, ws, wss");
        }

        $this->uri = $uri;

        $this->loop = $loop;
        $this->streams = new SplObjectStorage();
        $this->connections = new SplObjectStorage();

        $this->context = stream_context_create();
        $this->logger = $logger;
    }

    /**
     * @return resource
     */
    public function getStreamContext()
    {
        return $this->context;
    }

    /**
     * @param $context
     */
    public function setStreamContext($context)
    {
        $this->context = $context;
    }

    /**
     * Start the server
     */
    public function bind()
    {

        $err = $errno = 0;

        $this->flashPolicyFile =
            str_replace('to-ports="*', 'to-ports="' . $this->uri->getPort() ?: 80, $this->flashPolicyFile);

        $serverSocket = stream_socket_server(
            $this->uri->toString(),
            $errno,
            $err,
            STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
            $this->context
        );

        $this->logger->notice(sprintf("phpws listening on %s", $this->uri->toString()));

        if ($serverSocket == false) {
            $this->logger->err("Error: $err");
            return;
        }

        $timeOut = &$this->purgeUserTimeOut;
        $sockets = $this->streams;
        $that = $this;
        $logger = $this->logger;

        $this->loop->addReadStream($serverSocket, function ($serverSocket) use ($that, $logger, $sockets) {
            $newSocket = stream_socket_accept($serverSocket);

            if (false === $newSocket) {
                return;
            }

            stream_set_blocking($newSocket, 0);
            $client = new WebSocketConnection($newSocket, $that->loop, $logger);
            $sockets->attach($client);

            $client->on("handshake", function (Handshake $request) use ($that, $client) {
                $that->emit("handshake", [$client->getTransport(), $request]);
            });

            $client->on("connect", function () use ($that, $client, $logger) {
                $con = $client->getTransport();
                $that->getConnections()->attach($con);
                $that->emit("connect", ["client" => $con]);
            });

            $client->on("message", function ($message) use ($that, $client, $logger) {
                $connection = $client->getTransport();
                $that->emit("message", ["client" => $connection, "message" => $message]);
            });

            $client->on("close", function () use ($that, $client, $logger, &$sockets, $client) {
                $sockets->detach($client);
                $connection = $client->getTransport();

                if ($connection) {
                    $that->getConnections()->detach($connection);
                    $that->emit("disconnect", ["client" => $connection]);
                }
            });

            $client->on("flashXmlRequest", function () use ($that, $client) {
                $client->getTransport()->sendString($that->flashPolicyFile);
                $client->close();
            });
        });

        $this->loop->addPeriodicTimer(5, function () use ($timeOut, $sockets, $that) {

            # Lets send some pings
            foreach ($that->getConnections() as $c) {
                if ($c instanceof WebSocketTransportHybi) {
                    $c->sendFrame(WebSocketFrame::create(WebSocketOpcode::PING_FRAME));
                }
            }

            $currentTime = time();
            if ($timeOut == null) {
                return;
            }

            foreach ($sockets as $s) {
                if ($currentTime - $s->getLastChanged() > $timeOut) {
                    $s->close();
                }
            }
        });
    }

    /**
     * @return \Devristo\Phpws\Protocol\WebSocketTransportInterface[]|SplObjectStorage
     */
    public function getConnections()
    {
        return $this->connections;
    }
}
