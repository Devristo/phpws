<?php

namespace Devristo\Phpws\Server\UriHandler;

use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\WebSocketTransportInterface;
use Devristo\Phpws\Server\WebSocketServer;
use Evenement\EventEmitter;
use SplObjectStorage;
use Zend\Log\LoggerInterface;

class WebSocketUriHandler extends EventEmitter implements WebSocketUriHandlerInterface
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

        $this->on("message", array($this, 'onMessage'));
        $this->on("disconnect", array($this, 'onDisconnect'));
        $this->on("connect", array($this, 'onConnect'));
    }

    public function addConnection(WebSocketTransportInterface $user)
    {
        $this->users->attach($user);
    }

    public function removeConnection(WebSocketTransportInterface $user)
    {
        $this->users->detach($user);
    }

    public function onDisconnect(WebSocketTransportInterface $user)
    {

    }

    public function onConnect(WebSocketTransportInterface $user){

    }

    public function onMessage(WebSocketTransportInterface $user, WebSocketMessageInterface $msg)
    {

    }

    /**
     * @return \Devristo\Phpws\Protocol\WebSocketTransportInterface[]|SplObjectStorage
     */
    public function getConnections()
    {
        return $this->users;
    }

}