<?php

namespace Devristo\Phpws\Server\UriHandler;

use Devristo\Phpws\Messaging\IWebSocketMessage;
use Devristo\Phpws\Protocol\IWebSocketConnection;
use Devristo\Phpws\Server\WebSocketServer;
use SplObjectStorage;
use Zend\Log\LoggerInterface;

abstract class WebSocketUriHandler implements IWebSocketUriHandler
{

    /**
     *
     * Enter description here ...
     * @var SplObjectStorage
     */
    protected $users;

    /**
     *
     * Enter description here ...
     * @var WebSocketServer
     */
    protected $server;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct($logger)
    {
        $this->users = new SplObjectStorage();
        $this->logger = $logger;
    }

    public function addConnection(IWebSocketConnection $user)
    {
        $this->users->attach($user);
    }

    public function removeConnection(IWebSocketConnection $user)
    {
        $this->users->detach($user);
        $this->onDisconnect($user);
    }

    public function setServer(WebSocketServer $server)
    {
        $this->server = $server;
    }

    public function send(IWebSocketConnection $client, $str)
    {
        return $client->sendString($str);
    }

    public function onDisconnect(IWebSocketConnection $user)
    {

    }

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg)
    {

    }

    //abstract public function onMessage(WebSocketUser $user, IWebSocketMessage $msg);

    public function getConnections()
    {
        return $this->users;
    }

}