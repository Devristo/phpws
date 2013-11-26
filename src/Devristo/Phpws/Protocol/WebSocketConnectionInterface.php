<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:44 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Protocol;

use Devristo\Phpws\Framing\WebSocketFrameInterface;
use Devristo\Phpws\Messaging\WebSocketMessageInterface;

interface WebSocketConnectionInterface
{

    public function getId();

    public function sendHandshakeResponse();

    public function setRole($role);

    public function onData($data);

    public function sendFrame(WebSocketFrameInterface $frame);

    public function sendMessage(WebSocketMessageInterface $msg);

    public function sendString($msg);

    public function getHeaders();

    public function getUriRequested();

    public function getCookies();

    public function getIp();

    public function close();
}