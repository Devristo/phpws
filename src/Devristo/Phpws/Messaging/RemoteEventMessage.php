<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 27-11-13
 * Time: 18:41
 */

namespace Devristo\Phpws\Messaging;


class RemoteEventMessage implements MessageInterface {
    protected $tag;
    protected $data;
    protected $event;
    protected $room;

    public function __construct(){
        $this->tag = uniqid("server-");
    }

    public static function create($room, $event, $data)
    {
        $message = new RemoteEventMessage();
        $message->setRoom($room);
        $message->setEvent($event);
        $message->setData($data);

        return $message;
    }

    /**
     * @param mixed $room
     */
    public function setRoom($room)
    {
        $this->room = $room;
    }

    /**
     * @return mixed
     */
    public function getRoom()
    {
        return $this->room;
    }

    /**
     * @param mixed $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $tag
     */
    public function setTag($tag)
    {
        $this->tag = $tag;
    }

    /**
     * @return mixed
     */
    public function getTag()
    {
        return $this->tag;
    }

    public static function fromJson($jsonString){
        $data = json_decode($jsonString);

        if(!$data || !property_exists($data, 'event') || !property_exists($data, 'tag') || !property_exists($data, 'room'))
            throw new \InvalidArgumentException("Not a valid JSON RemoteEvent object");

        $JsonMessage = new RemoteEventMessage();

        if(property_exists($data, 'data'))
            $JsonMessage->setData($data->data);
        else $JsonMessage->setData(null);

        $JsonMessage->setTag($data->tag);
        $JsonMessage->setEvent($data->event);
        $JsonMessage->setRoom($data->room);

        return $JsonMessage;
    }

    public function toJson(){
        return json_encode(array(
            'tag' => $this->getTag(),
            'data' => $this->getData(),
            'room' => $this->getRoom(),
            'event' => $this->getEvent()
        ));
    }

    /**
     * @param mixed $event
     */
    public function setEvent($event)
    {
        $this->event = $event;
    }

    /**
     * @return mixed
     */
    public function getEvent()
    {
        return $this->event;
    }
} 