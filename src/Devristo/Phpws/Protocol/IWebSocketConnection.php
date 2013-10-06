<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:44 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Protocol;

use Devristo\Phpws\Framing\IWebSocketFrame;
use Devristo\Phpws\Messaging\IWebSocketMessage;

interface IWebSocketConnection
{

    public function sendHandshakeResponse();

    public function setRole($role);

    public function readFrame($data);

    public function sendFrame(IWebSocketFrame $frame);

    public function sendMessage(IWebSocketMessage $msg);

    public function sendString($msg);

    public function getHeaders();

    public function getUriRequested();

    public function getCookies();

    public function getIp();

    public function disconnect();
}