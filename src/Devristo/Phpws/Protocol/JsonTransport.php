<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 27-11-13
 * Time: 18:36
 */

namespace Devristo\Phpws\Protocol;

use Devristo\Phpws\Messaging\JsonMessage;
use Evenement\EventEmitter;

class JsonTransport extends EventEmitter implements TransportInterface{
    /**
     * @var TransportInterface
     */
    protected $carrierProtocol;

    public function __construct(){
        $that = $this;
    }

    public function replyTo(JsonMessage $message, $data){
        $reply = new JsonMessage();
        $reply->setTag($message->getTag());
        $reply->setData($data);

        $this->carrierProtocol->sendString($reply->toJson());
    }

    public function setCarrier(TransportInterface $carrierProtocol)
    {
        $this->carrierProtocol = $carrierProtocol;
    }

    public function onData($string)
    {
        $jsonMessage = JsonMessage::fromJson($string);
        $this->emit("message", array($this, $jsonMessage));
    }

    public function sendString($string)
    {
        $message = new JsonMessage();
        $message->setTag(null);
        $message->setData($string);

        $this->carrierProtocol->sendString($message->toJson());
    }

    public function getId(){
        return $this->carrierProtocol->getId();
    }
}