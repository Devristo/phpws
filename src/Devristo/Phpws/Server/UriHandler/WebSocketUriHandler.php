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

        $this->on("message", [$this, 'onMessage']);
        $this->on("disconnect", [$this, 'onDisconnect']);
        $this->on("connect", [$this, 'onConnect']);
    }

    /**
     * @param WebSocketTransportInterface $user
     * @return void
     */
    public function addConnection(WebSocketTransportInterface $user)
    {
        $this->users->attach($user);
    }

    /**
     * @param WebSocketTransportInterface $user
     * @return void
     */
    public function removeConnection(WebSocketTransportInterface $user)
    {
        $this->users->detach($user);
    }

    /**
     * @param WebSocketTransportInterface $user
     * @return void
     */
    public function onDisconnect(WebSocketTransportInterface $user)
    {

    }

    /**
     * @param WebSocketTransportInterface $user
     * @return void
     */
    public function onConnect(WebSocketTransportInterface $user)
    {

    }

    /**
     * @param WebSocketTransportInterface $user
     * @param WebSocketMessageInterface $msg
     * @return void
     */
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
