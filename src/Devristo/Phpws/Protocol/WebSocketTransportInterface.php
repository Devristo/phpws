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
use Zend\Http\Request;

interface WebSocketTransportInterface extends TransportInterface
{

    public function getId();

    public function respondTo(Request $request);

    public function setRole($role);

    public function onData($data);

    public function sendString($msg);

    public function getIp();

    public function close();
}