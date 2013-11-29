<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 27-11-13
 * Time: 18:36
 */

namespace Devristo\Phpws\Protocol;

use Devristo\Phpws\Messaging\JsonMessage;
use Devristo\Phpws\Messaging\MessageInterface;
use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use Zend\Log\LoggerInterface;

class JsonTransport extends EventEmitter implements TransportInterface{
    /**
     * @var TransportInterface
     */
    protected $carrierProtocol;
    protected $logger;

    /**
     * @var Deferred[]
     */
    protected $deferred = array();
    protected $timers = array();

    public function __construct(TransportInterface $carrierProtocol, LoopInterface $loop, LoggerInterface $logger){
        $that = $this;
        $this->logger = $logger;
        $this->loop = $loop;
        $this->carrierProtocol = $carrierProtocol;

        $deferreds = &$this->deferred;
        $timers = &$this->timers;

        $carrierProtocol->on("message", function(MessageInterface $message) use (&$deferreds, &$timers, &$loop, $that, $logger){
            $string = $message->getData();

            try{
                $jsonMessage = JsonMessage::fromJson($string);

                $tag = $jsonMessage->getTag();

                if(array_key_exists($tag, $deferreds)){
                    $deferred = $deferreds[$tag];
                    unset($deferreds[$tag]);

                    if(array_key_exists($tag, $timers)){
                        $loop->cancelTimer($timers[$tag]);
                        unset($timers[$tag]);
                    }
                    $deferred->resolve($jsonMessage);
                }else
                    $that->emit("message", array($jsonMessage));

            }catch(\Exception $e){
                $logger->err("Exception while parsing JsonMessage: ".$e->getMessage());
            }
        });
    }

    public function replyTo(JsonMessage $message, $data){
        $reply = new JsonMessage();
        $reply->setTag($message->getTag());
        $reply->setData($data);

        $this->carrierProtocol->sendString($reply->toJson());
    }

    public function whenResponseTo($data, $timeout=null){
        $deferred = new Deferred();
        $tag = uniqid("server-");
        $this->deferred[$tag] = $deferred;

        $message = new JsonMessage();
        $message->setTag($tag);
        $message->setData($data);

        $this->carrierProtocol->sendString($message->toJson());
        $this->logger->debug(sprintf("Awaiting response to '%s' with %s",  $data, $timeout ? "timeout $timeout" : 'no timeout' ));

        if($timeout){
            $list = &$this->deferred;
            $logger = $this->logger;

            $this->timers[$tag] = $this->loop->addTimer($timeout, function() use($deferred, &$list, $tag, $logger){
                unset($list[$tag]);
                $logger->debug("Request with tag $tag has timed out");
                $deferred->reject("Timeout occurred");
            });
        }

        return $deferred->promise();
    }

    public function sendString($string)
    {
        $message = new JsonMessage();
        $message->setTag(uniqid("server-"));
        $message->setData($string);

        $this->carrierProtocol->sendString($message->toJson());
    }
}