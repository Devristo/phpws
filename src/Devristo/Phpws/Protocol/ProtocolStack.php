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
     * @param TransportInterface[] $stack
     * @throws \InvalidArgumentException
     */
    public function __construct(WebSocketServer $server, $stack)
    {
        $this->server = $server;
        $this->stack = $stack;

        if (count($stack) < 1)
            throw new \InvalidArgumentException("Stack should be a non empty array");

        $first = $stack[0];
        $server->on("message", function (TransportInterface $interface, MessageInterface $message) use ($first) {
            $first->setCarrier($interface);
            $first->onData($message->getData());
        });

        for ($i = 0; $i < count($stack) - 1; $i++) {
            $carrier = $stack[$i];
            $next = $stack[$i + 1];

            $next->setCarrier($carrier);

            $carrier->on("message", function (TransportInterface $interface, MessageInterface $message) use ($next) {
                $next->onData($message->getData());
            });
        }

        $last = $stack[count($stack) - 1];
        $that = $this;

        $last->on("message", function (TransportInterface $interface, MessageInterface $message) use ($that) {
            $that->emit("message", array($interface, $message));
        });

        $server->on("connect", function(WebSocketConnectionInterface $user) use($that, $first, $last){
            $first->setCarrier($user);
            $that->emit("connect", array($last));
        });

        $server->on("disconnect", function(WebSocketConnectionInterface $user) use($that, $last){
            $that->emit("disconnect", array($last));
        });
    }
} 