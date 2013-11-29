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

class ServerProtocolStack extends EventEmitter
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

        $ws2stack = array();

        // A specification can be either a fully qualified class name or a lambda expression: TransportInterface -> TransportInterface
        $instantiator = function($spec, TransportInterface $carrier){
            if(is_string($spec)){
                $transport = new $spec($carrier);
            } elseif(is_callable($spec)){
                $transport = $spec($carrier);
            }
            return $transport;
        };

        $server->on("connect", function(WebSocketTransportInterface $webSocketTransport) use($that, $stackSpecs, $server, &$ws2stack, $instantiator){
            $carrier = $webSocketTransport;
            $first = null;

            /**
             * @var $stack TransportInterface[]
             */
            $stack = array($carrier);

            // Instantiate transports
            $i = 0;
            do{
                $transport = $instantiator($stackSpecs[$i], $carrier);
                $carrier = $transport;
                $stack[] = $transport;

                $i++;
            }while($i < count($stackSpecs));

            $first = $stack[1];
            $last = $stack[count($stack) - 1];

            // Remember the stack for this websocket connection, used to trigger disconnect event
            $stackCollection = new StackTransport($stack);
            $ws2stack[$webSocketTransport->getId()] = $stackCollection;

            // Link the message event to the next data event
            for($i=0; $i<count($stack)-1; $i++){
                $next = $stack[$i+1];
                $stack[$i]->on("message", function (MessageInterface $message) use ($next) {
                    $next->handleData($message->getData());
                });
            }

            // When the last protocol produces a message, emit it on our ProtocolStack
            $last->on("message", function (MessageInterface $message) use ($that, $stackCollection) {
                $that->emit("message", array($stackCollection, $message));
            });

            $that->emit("connect", array($stackCollection));
        });

        $server->on("disconnect", function(WebSocketTransportInterface $user) use($that, &$ws2stack){
            $stack = array_key_exists($user->getId(), $ws2stack) ? $ws2stack[$user->getId()] : null;

            if($stack)
                $that->emit("disconnect", array($stack));
        });
    }
} 