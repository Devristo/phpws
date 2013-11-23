<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 23-11-13
 * Time: 13:12
 */

namespace Devristo\Phpws\Server;


use Devristo\Phpws\Messaging\IWebSocketMessage;
use Devristo\Phpws\Protocol\IWebSocketConnection;

class WebSocketServerObserver implements IWebSocketServerObserver {

    public function onConnect(IWebSocketConnection $user)
    {
    }

    public function onMessage(IWebSocketConnection $user, IWebSocketMessage $msg)
    {
    }

    public function onDisconnect(IWebSocketConnection $user)
    {
    }
}