<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:43 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Protocol;

use React\Socket\ConnectionInterface;
use Zend\Http\Request;
use Zend\Log\LoggerInterface;

class WebSocketTransportFactory
{

    public static function fromSocketData(ConnectionInterface $socket, $data, LoggerInterface $logger)
    {
        // Check whether we have a Adobe Flash Policy file Request
        if(strpos($data, '<policy-file-request/>') === 0){
            $s = new WebSocketTransportFlash($socket, $data);
            $s->setLogger($logger);

            return $s;
        }

        $request = Request::fromString($data);

        if ($request->getHeader('Sec-Websocket-Key1')) {
            $s = new WebSocketTransportHixie($socket, $request, $data);
            $s->setLogger($logger);
        } else{
            $s = new WebSocketTransportHybi($socket, $request);
            $s->setLogger($logger);
        }


        return $s;
    }
}