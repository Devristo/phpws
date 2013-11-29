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

class WebSocketTransportFlash extends WebSocketTransport
{

    public function __construct($socket, $data)
    {
        $this->_socket = $socket;

        $this->emit("flashXmlRequest");
    }

    public function sendString($msg)
    {
        $this->_socket->write($msg);
    }

    public function close()
    {
        $this->_socket->close();
    }

    public function sendHandshakeResponse()
    {
        throw new Exception("Not supported!");
    }

    public function handleData($data)
    {
        throw new Exception("Not supported!");
    }
}