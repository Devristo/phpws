<?php

namespace Devristo\Phpws\Server\UriHandler;

use Devristo\Phpws\Messaging\IWebSocketMessage;
use Devristo\Phpws\Protocol\WebSocketConnectionInterface;
use Devristo\Phpws\Server\WebSocketServer;
use SplObjectStorage;
use Zend\Log\LoggerInterface;

abstract class WebSocketUriHandler implements WebSocketUriHandlerInterface
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

    public function addConnection(WebSocketConnectionInterface $user)
    {
        $this->users->attach($user);
    }

    public function removeConnection(WebSocketConnectionInterface $user)
    {
        $this->users->detach($user);
        $this->onDisconnect($user);
    }

    public function setServer(WebSocketServer $server)
    {
        $this->server = $server;
    }

    public function send(WebSocketConnectionInterface $client, $str)
    {
        return $client->sendString($str);
    }

    public function onDisconnect(WebSocketConnectionInterface $user)
    {

    }

    public function onMessage(WebSocketConnectionInterface $user, IWebSocketMessage $msg)
    {

    }

    //abstract public function onMessage(WebSocketUser $user, IWebSocketMessage $msg);

    public function getConnections()
    {
        return $this->users;
    }

}