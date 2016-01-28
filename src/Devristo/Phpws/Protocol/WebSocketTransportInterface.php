<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:44 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Protocol;

use Zend\Http\Request;
use Zend\Http\Response;

interface WebSocketTransportInterface extends TransportInterface
{
    public function getId();

    /**
     * @param Request $request
     * @return mixed
     */
    public function respondTo(Request $request);

    /**
     * @return Request
     */
    public function getHandshakeRequest();

    /**
     * @return Response
     */
    public function getHandshakeResponse();

    /**
     * @param $data
     * @return mixed
     */
    public function handleData(&$data);

    /**
     * @return mixed
     */
    public function getIp();

    /**
     * @return mixed
     */
    public function close();

    /**
     * @param $key
     * @param $value
     * @return mixed
     */
    public function setData($key, $value);

    /**
     * @param $key
     * @return mixed
     */
    public function getData($key);
}
