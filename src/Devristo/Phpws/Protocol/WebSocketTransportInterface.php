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
use Zend\Http\Response;

interface WebSocketTransportInterface extends TransportInterface
{

    public function getId();

    public function respondTo(Request $request);

    /**
     * @return Request
     */
    public function getHandshakeRequest();

    /**
     * @return Response
     */
    public function getHandshakeResponse();

    public function handleData(&$data);

    public function getIp();

    public function close();

    public function setData($key, $value);

    public function getData($key);
}
