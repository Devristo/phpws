<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:46 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Server\UriHandler;

use Devristo\Phpws\Messaging\IWebSocketMessage;
use Devristo\Phpws\Protocol\WebSocketConnectionInterface;
use Devristo\Phpws\Server\WebSocketServer;

interface WebSocketUriHandlerInterface
{

    public function addConnection(WebSocketConnectionInterface $user);

    public function removeConnection(WebSocketConnectionInterface $user);

    public function onMessage(WebSocketConnectionInterface $user, IWebSocketMessage $msg);

    public function setServer(WebSocketServer $server);

    /**
     * @return WebSocketConnectionInterface[]
     */
    public function getConnections();
}