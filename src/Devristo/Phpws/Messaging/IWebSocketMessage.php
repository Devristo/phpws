<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:42 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Messaging;
use Devristo\Phpws\Framing\IWebSocketFrame;
use Devristo\Phpws\Framing\WebSocketFrame;
use Exception;

/**
 *
 * Interface for incoming and outgoing messages
 * @author Chris
 *
 */
interface IWebSocketMessage
{

    /**
     * Retreive an array of frames of which this message is composed
     *
     * @return WebSocketFrame[]
     */
    public function getFrames();

    /**
     * Set the body of the message
     * This should recompile the array of frames
     * @param string $data
     */
    public function setData($data);

    /**
     * Retreive the body of the message
     * @return string
     */
    public function getData();

    /**
     * Create a new message
     * @param string $data Content of the message to be created
     */
    public static function create($data);

    /**
     * Check if we have received the last frame of the message
     *
     * @return bool
     */
    public function isFinalised();

    /**
     * Create a message from it's first frame
     * @param IWebSocketFrame $frame
     * @throws Exception
     */
    public static function fromFrame(IWebSocketFrame $frame);
}