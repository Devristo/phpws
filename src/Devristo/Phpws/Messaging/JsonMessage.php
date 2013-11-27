<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 27-11-13
 * Time: 18:41
 */

namespace Devristo\Phpws\Messaging;


class JsonMessage implements MessageInterface {
    protected $tag;
    protected $data;

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
        $data = json_decode($jsonString,true);

        $JsonMessage = new JsonMessage();
        $JsonMessage->setData($data['data']);
        $JsonMessage->setTag($data['tag']);

        return $JsonMessage;
    }

    public function toJson(){
        return json_encode(array(
            'tag' => $this->getTag(),
            'data' => $this->getData()
        ));
        return $JsonMessage;
    }
} 