<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 27-11-13
 * Time: 18:36
 */

namespace Devristo\Phpws\RemoteEvent;

use Devristo\Phpws\Messaging\RemoteEventMessage;
use Devristo\Phpws\Messaging\MessageInterface;
use Devristo\Phpws\Protocol\TransportInterface;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use Zend\Log\LoggerInterface;

class RemoteEventTransport extends EventEmitter implements TransportInterface
{
    /**
     * @var TransportInterface
     */
    protected $carrierProtocol;
    protected $logger;

    /**
     * @var Deferred[]
     */
    protected $deferred = [];
    protected $timers = [];

    protected $actionEmitter;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @return EventEmitter
     */
    public function remoteEvent()
    {
        return $this->actionEmitter;
    }

    public function __construct(TransportInterface $carrierProtocol, LoopInterface $loop, LoggerInterface $logger)
    {
        $that = $this;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->carrierProtocol = $carrierProtocol;

        $this->actionEmitter = new EventEmitter();

        $deferreds = &$this->deferred;
        $timers = &$this->timers;

        $carrierProtocol->on(
            "message",
            function (MessageInterface $message) use (&$deferreds, &$timers, &$loop, $that, $logger) {
                $string = $message->getData();

                try {
                    $jsonMessage = RemoteEventMessage::fromJson($string);

                    $tag = $jsonMessage->getTag();

                    if (array_key_exists($tag, $deferreds)) {
                        $deferred = $deferreds[$tag];
                        unset($deferreds[$tag]);

                        if (array_key_exists($tag, $timers)) {
                            $loop->cancelTimer($timers[$tag]);
                            unset($timers[$tag]);
                        }
                        $deferred->resolve($jsonMessage);
                    } else {
                        $that->remoteEvent()->emit($jsonMessage->getEvent(), [$jsonMessage]);
                    }

                    $that->emit("message", [$jsonMessage]);

                } catch (\Exception $e) {
                    $logger->err("Exception while parsing JsonMessage: " . $e->getMessage());
                }
            }
        );
    }

    /**
     * @param RemoteEventMessage $message
     * @param $data
     */
    public function replyTo(RemoteEventMessage $message, $data)
    {
        $reply = new RemoteEventMessage();
        $reply->setRoom($message->getRoom());
        $reply->setTag($message->getTag());
        $reply->setEvent($message->getEvent());
        $reply->setData($data);

        $this->carrierProtocol->sendString($reply->toJson());
    }

    /**
     * @param RemoteEventMessage $message
     * @param null $timeout
     * @return \React\Promise\Promise|\React\Promise\PromiseInterface
     */
    public function whenResponseTo(RemoteEventMessage $message, $timeout = null)
    {
        $deferred = new Deferred();

        $tag = $message->getTag();
        $this->deferred[$tag] = $deferred;

        $this->carrierProtocol->sendString($message->toJson());
        $this->logger->debug(sprintf(
            "Awaiting response to '%s'%s with %s",
            $message->getData(),
            $message->getRoom() ? " in room " . $message->getRoom() : '',
            $timeout ? "timeout $timeout" : 'no timeout'
        ));

        if ($timeout) {
            $list = &$this->deferred;
            $logger = $this->logger;

            $this->timers[$tag] = $this->loop->addTimer($timeout, function () use ($deferred, &$list, $tag, $logger) {
                unset($list[$tag]);
                $logger->debug("Request with tag $tag has timed out");
                $deferred->reject("Timeout occurred");
            });
        }

        return $deferred->promise();
    }

    /**
     * @param $room
     * @param $event
     * @param $data
     */
    public function sendEmit($room, $event, $data)
    {
        $message = RemoteEventMessage::create($room, $event, $data);
        $this->send($message);
    }

    /**
     * @param $string
     */
    public function sendString($string)
    {
        $message = new RemoteEventMessage();
        $message->setTag(uniqid("server-"));
        $message->setData($string);

        $this->send($message);
    }

    /**
     * @param RemoteEventMessage $message
     */
    public function send(RemoteEventMessage $message)
    {
        $this->carrierProtocol->sendString($message->toJson());
    }
}
