<?php

namespace Devristo\Phpws\Client;

use Devristo\Phpws\Exceptions\WebSocketInvalidUrlScheme;
use Devristo\Phpws\Framing\WebSocketFrameInterface;
use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketOpcode;
use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Devristo\Phpws\Protocol\Handshake;
use Devristo\Phpws\Protocol\WebSocketTransport;
use Devristo\Phpws\Protocol\WebSocketTransportHybi;
use Devristo\Phpws\Protocol\WebSocketConnection;
use Devristo\Phpws\Reflection\FullAccessWrapper;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Log\LoggerInterface;
use Zend\Uri\Uri;

class WebSocket extends EventEmitter
{
    const STATE_HANDSHAKE_SENT = 0;
    const STATE_CONNECTED = 1;
    const STATE_CLOSING = 2;
    const STATE_CLOSED = 3;

    protected $state = self::STATE_CLOSED;

    protected $url;

    /**
     * @var WebSocketConnection
     */
    protected $stream;
    protected $socket;


    protected $request;
    protected $response;

    /**
     * @var WebSocketTransport
     */
    protected $transport;

    protected $headers;
    protected $loop;

    protected $logger;

    protected $isClosing = false;

    protected $streamOptions;

    /** @var \React\Dns\Resolver\Resolver */
    protected $dns;

    /**
     * @param string $url
     * @param LoopInterface $loop
     * @param LoggerInterface $logger
     * @param array|null $streamOptions
     * @throws WebSocketInvalidUrlScheme
     */
    public function __construct(
        $url,
        LoopInterface $loop,
        LoggerInterface $logger,
        array $streamOptions = null
    ) {
        $this->logger = $logger;
        $this->loop = $loop;
        $this->streamOptions = $streamOptions;
        $parts = parse_url($url);

        $this->url = $url;

        if (in_array($parts['scheme'], ['ws', 'wss']) === false) {
            throw new WebSocketInvalidUrlScheme();
        }

        $dnsResolverFactory = new \React\Dns\Resolver\Factory();
        $this->dns = $dnsResolverFactory->createCached('8.8.8.8', $loop);
    }

    /**
     * @param null $timeOut
     * @return \React\Promise\Promise|\React\Promise\PromiseInterface
     */
    public function open($timeOut = null)
    {
        /**
         * @var $that self
         */
        $that = new FullAccessWrapper($this);

        $uri = new Uri($this->url);

        $isSecured = 'wss' === $uri->getScheme();
        $defaultPort = $isSecured ? 443 : 80;

        $connector = new Connector($this->loop, $this->dns, $this->streamOptions);

        if ($isSecured) {
            $connector = new \React\SocketClient\SecureConnector($connector, $this->loop);
        }

        $deferred = new Deferred();

        $connector->create($uri->getHost(), $uri->getPort() ?: $defaultPort)
            ->then(function (\React\Stream\DuplexStreamInterface $stream) use ($that, $uri, $deferred, $timeOut) {

                if ($timeOut) {
                    $timeOutTimer = $that->loop->addTimer($timeOut, function () use ($deferred, $stream, $that) {
                        $stream->close();
                        $that->logger->notice("Timeout occured, closing connection");
                        $that->emit("error");
                        $deferred->reject("Timeout occured");
                    });
                } else {
                    $timeOutTimer = null;
                }

                $transport = new WebSocketTransportHybi($stream);
                $transport->setLogger($that->logger);
                $that->transport = $transport;
                $that->stream = $stream;

                $stream->on("close", function () use ($that) {
                    $that->isClosing = false;
                    $that->state = WebSocket::STATE_CLOSED;
                });

                // Give the chance to change request
                $transport->on("request", function (Request $handshake) use ($that) {
                    $that->emit("request", func_get_args());
                });

                $transport->on("handshake", function (Handshake $handshake) use ($that) {
                    $that->request = $handshake->getRequest();
                    $that->response = $handshake->getRequest();

                    $that->emit("handshake", [$handshake]);
                });

                $transport->on("connect", function () use (&$state, $that, $transport, $timeOutTimer, $deferred) {
                    if ($timeOutTimer) {
                        $timeOutTimer->cancel();
                    }

                    $deferred->resolve($transport);
                    $that->state = WebSocket::STATE_CONNECTED;
                    $that->emit("connect");

                });

                $transport->on('message', function ($message) use ($that, $transport) {
                    $that->emit("message", ["message" => $message]);
                });

                $transport->initiateHandshake($uri);
                $that->state = WebSocket::STATE_HANDSHAKE_SENT;
            }, function ($reason) use ($that, $deferred) {
                $deferred->reject($reason);
                $that->logger->err($reason);
            });

        return $deferred->promise();

    }

    /**
     * @param string $string
     */
    public function send($string)
    {
        $this->transport->sendString($string);
    }

    /**
     * @param WebSocketMessageInterface $msg
     */
    public function sendMessage(WebSocketMessageInterface $msg)
    {
        $this->transport->sendMessage($msg);
    }

    /**
     * @param WebSocketFrameInterface $frame
     */
    public function sendFrame(WebSocketFrameInterface $frame)
    {
        $this->transport->sendFrame($frame);
    }

    /**
     * @return void
     */
    public function close()
    {
        if ($this->isClosing) {
            return;
        }

        $this->isClosing = true;
        $this->sendFrame(WebSocketFrame::create(WebSocketOpcode::CLOSE_FRAME));

        $this->state = self::STATE_CLOSING;
        $stream = $this->stream;

        $closeTimer = $this->loop->addTimer(5, function () use ($stream) {
            $stream->close();
        });

        $loop = $this->loop;
        $stream->once("close", function () use ($closeTimer, $loop) {
            if ($closeTimer) {
                $loop->cancelTimer($closeTimer);
            }
        });
    }
}
