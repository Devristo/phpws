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
use Devristo\Phpws\Protocol\WebSocketStream;

interface WebSocketObserver
{

    public function onDisconnect(WebSocketStream $s);

    public function onConnectionEstablished(WebSocketStream $s);

    public function onMessage(IWebSocketConnection $s, IWebSocketMessage $msg);

    public function onFlashXMLRequest(WebSocketConnectionFlash $connection);
}