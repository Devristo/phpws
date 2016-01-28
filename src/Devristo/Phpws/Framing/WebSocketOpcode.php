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
 * Enum-like construct containing all opcodes defined in the WebSocket protocol
 * @author Chris
 *
 */
class WebSocketOpcode
{
    const __DEFAULT = 0;
    const CONTINUATION_FRAME = 0x00;
    const TEXT_FRAME = 0x01;
    const BINARY_FRAME = 0x02;
    const CLOSE_FRAME = 0x08;
    const PING_FRAME = 0x09;
    const PONG_FRAME = 0x0A;

    private function __construct()
    {

    }

    /**
     * Check if a opcode is a control frame. Control frames should be handled internally by the server.
     * @param int $type
     *
     * @return bool whether the opcode is considered to be a control frame or not
     */
    public static function isControlFrame($type)
    {
        $controlFrames = [self::CLOSE_FRAME, self::PING_FRAME, self::PONG_FRAME];

        return in_array($type, $controlFrames);
    }
}
