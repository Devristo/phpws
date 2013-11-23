<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 21-11-13
 * Time: 18:19
 */

namespace Devristo\Phpws\Server;


interface ISocketStream {
    public function onData($data);
    public function close();
    public function mayWrite();
    public function requestsWrite();
    public function getSocket();
    public function acceptConnection();
    public function isServer();
    public function isClosed();
} 