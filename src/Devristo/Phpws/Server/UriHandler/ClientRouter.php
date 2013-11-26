<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 26-11-13
 * Time: 18:19
 */

namespace Devristo\Phpws\Server\UriHandler;

use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\WebSocketConnection;
use Devristo\Phpws\Protocol\WebSocketConnectionInterface;
use Devristo\Phpws\Server\WebSocketServer;
use Zend\Log\LoggerInterface;

class ClientRouter {
    protected $handlers = array();
    protected $membership;
    public function __construct(WebSocketServer $server, LoggerInterface $logger){
        $this->server = $server;

        $this->membership = new \SplObjectStorage();

        /**
         * @var $membership \SplObjectStorage|WebSocketUriHandlerInterface[]
         */
        $membership = $this->membership;

        $that = $this;
        $server->on("connect", function(WebSocketConnectionInterface $client) use ($that, $logger, $membership){
            $handler = $that->matchConnection($client);

            if($handler){
                $logger->notice("We have added client {$client->getId()} to ".get_class($handler));
                $membership->attach($client, $handler);
                $handler->addConnection($client);
            }else
                $logger->err("Cannot route {$client->getId()} with request uri {$client->getUriRequested()}");
        });

        $server->on('disconnect', function(WebSocketConnectionInterface $client) use($that, $logger, $membership){
            if($membership->contains($client)){
                $handler = $membership[$client];
                $membership->detach($client);

                $logger->notice("We have removed client {$client->getId()} from".get_class($handler));

                $handler->removeConnection($client);
                $handler->emit("disconnect", array("client" => $client));

            } else {
                $logger->warn("Client {$client->getId()} not attached to any handler, so cannot remove it!");
            }
        });

        $server->on("message", function(WebSocketConnectionInterface $client, WebSocketMessageInterface $message) use($that, $logger, $membership){
            if($membership->contains($client)){
                $handler = $membership[$client];
                $handler->emit("message", compact('client', 'message'));
            } else {
                $logger->warn("Client {$client->getId()} not attached to any handler, so cannot forward the message!");
            }
        });

    }

    /**
     * @param WebSocketConnection $client
     * @return null|WebSocketUriHandlerInterface
     */
    public function matchConnection(WebSocketConnection $client){
        foreach($this->handlers as $key => $value ){
            if(preg_match($key,$client->getUriRequested()))
                return $value;
        }

        return null;
    }

    public function addUriHandler($matchPattern, WebSocketUriHandlerInterface $handler){
        $this->handlers[$matchPattern] = $handler;
    }
} 