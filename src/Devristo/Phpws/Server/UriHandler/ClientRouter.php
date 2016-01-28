<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 26-11-13
 * Time: 18:19
 */

namespace Devristo\Phpws\Server\UriHandler;

use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\WebSocketTransportInterface;
use Evenement\EventEmitter;
use Zend\Log\LoggerInterface;

class ClientRouter
{
    protected $handlers;
    protected $logger;

    protected $membership;

    /**
     * @param EventEmitter $server
     * @param LoggerInterface $logger
     */
    public function __construct(EventEmitter $server, LoggerInterface $logger)
    {
        $this->server = $server;
        $this->logger = $logger;
        $this->handlers = new \SplObjectStorage();
        $this->membership = new \SplObjectStorage();

        /**
         * @var $membership \SplObjectStorage|WebSocketUriHandlerInterface[]
         */
        $membership = $this->membership;

        $that = $this;

        $server->on(
            "connect",
            function (WebSocketTransportInterface $client) use ($that, $logger, $membership) {
                $handler = $that->matchConnection($client);

                if ($handler) {
                    $logger->notice("Added client {$client->getId()} to " . get_class($handler));
                    $membership->attach($client, $handler);
                    $handler->emit("connect", ["client" => $client]);
                    $handler->addConnection($client);
                } else {
                    $logger->err(
                        sprintf(
                            "Cannot route %s with request uri %s",
                            $client->getId(),
                            $client->getHandshakeRequest()->getUriString()
                        )
                    );
                }
            }
        );

        $server->on(
            'disconnect',
            function (WebSocketTransportInterface $client) use ($that, $logger, $membership) {
                if ($membership->contains($client)) {
                    $handler = $membership[$client];
                    $membership->detach($client);

                    $logger->notice("Removed client {$client->getId()} from" . get_class($handler));

                    $handler->removeConnection($client);
                    $handler->emit("disconnect", ["client" => $client]);

                } else {
                    $logger->warn("Client {$client->getId()} not attached to any handler, so cannot remove it!");
                }
            }
        );

        $server->on(
            "message",
            function (
                WebSocketTransportInterface $client,
                WebSocketMessageInterface $message
            ) use (
                $that,
                $logger,
                $membership
            ) {
                if ($membership->contains($client)) {
                    $handler = $membership[$client];
                    $handler->emit("message", compact('client', 'message'));
                } else {
                    $logger->warn(
                        sprintf(
                            "Client %s not attached to any handler, so cannot forward the message!",
                            $client->getId()
                        )
                    );
                }
            }
        );
    }

    /**
     * @param \Devristo\Phpws\Protocol\WebSocketTransportInterface $transport
     * @return null|WebSocketUriHandlerInterface
     */
    public function matchConnection(WebSocketTransportInterface $transport)
    {
        foreach ($this->handlers as $tester) {
            /** @var \Closure $tester */
            if ($tester($transport)) {
                return $this->handlers[$tester];
            }
        }

        return null;
    }

    /**
     * @param string|callable $tester Either a regexp or a callable function: WebSocketTransportInterface -> boolean
     * @param WebSocketUriHandlerInterface $handler
     * @throws \InvalidArgumentException
     */
    public function addRoute($tester, WebSocketUriHandlerInterface $handler)
    {
        if (is_string($tester)) {
            $tester = function (WebSocketTransportInterface $transport) use ($tester) {
                return preg_match($tester, $transport->getHandshakeRequest()->getUriString());
            };
        } elseif (!is_callable($tester))
            throw new \InvalidArgumentException("Tester should either be a regexp or a callable");

        $this->handlers->attach($tester, $handler);
    }
}
