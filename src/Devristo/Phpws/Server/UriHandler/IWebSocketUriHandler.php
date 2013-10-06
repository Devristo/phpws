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
use Devristo\Phpws\Protocol\IWebSocketConnection;
use Devristo\Phpws\Server\WebSocketServer;

interface IWebSocketUriHandler
{

    public function addConnection(IWebSocketConnection $user);

    public function removeConnection(IWebSocketConnection $user);

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg);

    public function setServer(WebSocketServer $server);

    /**
     * @return IWebSocketConnection[]
     */
    public function getConnections();
}