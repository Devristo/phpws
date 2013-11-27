<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 27-11-13
 * Time: 18:57
 */

namespace Devristo\Phpws\Protocol;


use Devristo\Phpws\Messaging\MessageInterface;
use Devristo\Phpws\Server\WebSocketServer;
use Evenement\EventEmitter;

class ProtocolStack extends EventEmitter
{
    protected $server;

    /**
     * @param WebSocketServer $server
     * @param TransportInterface[] $stackSpecs
     * @throws \InvalidArgumentException
     */
    public function __construct(WebSocketServer $server, $stackSpecs)
    {
        $that = $this;
        if (count($stackSpecs) < 1)
            throw new \InvalidArgumentException("Stack should be a non empty array");

        $ws2last = array();

        // A specification can be either a fully qualified class name or a lambda expression: TransportInterface -> TransportInterface
        $instantiator = function($spec, TransportInterface $carrier){
            if(is_string($spec)){
                $transport = new $spec($carrier);
            } elseif(is_callable($spec)){
                $transport = $spec($carrier);
            }
            return $transport;
        };

        $server->on("connect", function(WebSocketConnectionInterface $user) use($that, $stackSpecs, $server, &$ws2last, $instantiator){
            $carrier = $user;
            $first = null;

            /**
             * @var $stack TransportInterface[]
             */
            $stack = array();

            // Instantiate transports
            $i = 0;
            do{
                $transport = $instantiator($stackSpecs[$i], $carrier);
                $carrier = $transport;
                $stack[] = $transport;

                $i++;
            }while($i < count($stackSpecs));

            $first = $stack[0];
            $last = $stack[count($stack) - 1];

            // Remember the last protocol on the stack for this websocket connection, used to trigger disconnect event
            $ws2last[$user->getId()] = $last;

            // Link the first in the stack directly to the WebSocket server
            $server->on("message", function (TransportInterface $interface, MessageInterface $message) use ($first) {
                $first->onData($message->getData());
            });

            for($i=0; $i<count($stack)-1; $i++){
                $stack[$i]->on("message", function (TransportInterface $interface, MessageInterface $message) use ($first) {
                    $first->onData($message->getData());
                });
            }

            // When the last protocol produces a message, emit it on our ProtocolStack
            $last->on("message", function (TransportInterface $interface, MessageInterface $message) use ($that) {
                $that->emit("message", array($interface, $message));
            });

            $that->emit("connect", array($last));
        });

        $server->on("disconnect", function(WebSocketConnectionInterface $user) use($that, &$ws2last){
            $lastProtocolOnStack = array_key_exists($user->getId(), $ws2last) ? $ws2last[$user->getId()] : null;

            if($lastProtocolOnStack)
                $that->emit("disconnect", array($lastProtocolOnStack));
        });
    }
} 