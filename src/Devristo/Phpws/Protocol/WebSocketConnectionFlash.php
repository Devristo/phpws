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

class WebSocketConnectionFlash extends WebSocketConnection
{

    public function __construct($socket, $data)
    {
        $this->_socket = $socket;
        $this->_socket->onFlashXMLRequest($this);
    }

    public function sendString($msg)
    {
        $this->_socket->write($msg);
    }

    public function disconnect()
    {
        $this->_socket->disconnect();
    }

    public function sendHandshakeResponse()
    {
        throw new Exception("Not supported!");
    }

    public function readFrame($data)
    {
        throw new Exception("Not supported!");
    }
}