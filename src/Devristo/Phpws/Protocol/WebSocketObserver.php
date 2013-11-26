<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:46 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Protocol;

use Devristo\Phpws\Messaging\IWebSocketMessage;
use Devristo\Phpws\Protocol\WebSocketServerClient;

interface WebSocketObserver
{
    public function onDisconnect(WebSocketServerClient $s);

    public function onConnectionEstablished(WebSocketServerClient $s);

    public function onMessage(WebSocketConnectionInterface $s, IWebSocketMessage $msg);
}