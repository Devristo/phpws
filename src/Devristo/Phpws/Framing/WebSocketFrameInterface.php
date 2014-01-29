<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:41 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Framing;
/**
 * Interface for WebSocket frames. One or more frames compose a message.
 * In the case of the Hixie protocol, a message contains of one frame only
 *
 * @author Chris
 */
interface WebSocketFrameInterface
{

    /**
     * Serialize the frame so that it can be send over a socket
     * @return string Serialized binary string
     */
    public function encode();

    /**
     * Deserialize a binary string into a IWebSocketFrame
     * @param $string
     * @param null $head
     * @return string Serialized binary string
     */
    public static function decode(&$string);

    /**
     * @return string Payload Data inside the frame
     */
    public function getData();

    /**
     * @return int The frame type (opcode)
     */
    public function getType();

    /**
     * Create a frame by type and payload data
     * @param int $type
     * @param string $data
     *
     * @return \Devristo\Phpws\Framing\WebSocketFrameInterface
     */
    public static function create($type, $data = null);
}