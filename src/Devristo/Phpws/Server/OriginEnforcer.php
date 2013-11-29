<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 29-11-13
 * Time: 21:52
 */

namespace Devristo\Phpws\Server;
use Devristo\Phpws\Protocol\Handshake;

class OriginEnforcer {
    public function __construct(WebSocketServer $server, array $allowedOrigins){
        $server->on("handshake", function(Handshake $handshake) use ($allowedOrigins){
            $originHeader = $handshake->getRequest()->getHeader('Origin', null);
            $origin = $originHeader ? $originHeader->getFieldValue() : null;

            if(in_array("*", $allowedOrigins) || !in_array($origin, $allowedOrigins))
                $handshake->abort();
            else{
                // Confirm that the origin is allowed
                $handshake->getResponse()->getHeaders()->addHeaderLine("Access-Control-Allow-Origin", $origin);
            }
        });
    }
} 