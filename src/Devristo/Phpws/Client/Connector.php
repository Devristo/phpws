<?php

namespace Devristo\Phpws\Client;

use React\SocketClient\Connector as BaseConnector;
use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Resolver;
use React\Promise\When;

class Connector extends BaseConnector
{
    
    protected $contextOptions = array();
    
    public function __construct(LoopInterface $loop, Resolver $resolver, array $contextOptions = null) 
    {
        parent::__construct($loop, $resolver);
        
        $contextOptions = null === $contextOptions ? array() : $contextOptions;
        $this->contextOptions = $contextOptions;
    }
    
    protected function createStreamContext() 
    {
        return stream_context_create($this->contextOptions);
    }
    
    public function createSocketForAddress($address, $port, $hostName = null)
    {
        $url = $this->getSocketUrl($address, $port);

        $socket = stream_socket_client($url, $errno, $errstr, 0, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT, $this->createStreamContext());

        if (!$socket) {
            return When::reject(new \RuntimeException(
                sprintf("connection to %s:%d failed: %s", $address, $port, $errstr),
                $errno
            ));
        }

        stream_set_blocking($socket, 0);

        // wait for connection

        return $this
            ->waitForStreamOnce($socket)
            ->then(array($this, 'checkConnectedSocket'))
            ->then(array($this, 'handleConnectedSocket'));
    }    
    
}
