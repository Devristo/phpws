<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:44 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Protocol;

use Exception;
use Zend\Http\Request;

class WebSocketTransportFlash extends WebSocketTransport
{
    /**
     * WebSocketTransportFlash constructor.
     * @param \React\Stream\WritableStreamInterface $socket
     * @param $data
     */
    public function __construct($socket, $data)
    {
        $this->socket = $socket;

        $this->emit("flashXmlRequest");
    }

    /**
     * @param $msg
     */
    public function sendString($msg)
    {
        $this->socket->write($msg);
    }

    /**
     * @return void
     */
    public function close()
    {
        $this->socket->close();
    }

    /**
     * @throws Exception
     */
    public function sendHandshakeResponse()
    {
        throw new Exception("Not supported!");
    }

    /**
     * @param $data
     * @throws Exception
     */
    public function handleData(&$data)
    {
        throw new Exception("Not supported!");
    }

    /**
     * @param Request $request
     */
    public function respondTo(Request $request)
    {
        // TODO: Implement respondTo() method.
    }
}
