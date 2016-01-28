<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:41 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Framing;

use Devristo\Phpws\Exceptions\WebSocketFrameSizeMismatch;

/**
 * HYBIE WebSocketFrame
 *
 * @author Chris
 *
 */
class WebSocketFrame implements WebSocketFrameInterface
{

    // First Byte
    protected $FIN = 0;
    protected $RSV1 = 0;
    protected $RSV2 = 0;
    protected $RSV3 = 0;
    protected $opcode = WebSocketOpcode::TEXT_FRAME;
    // Second Byte
    protected $mask = 0;
    protected $payloadLength = 0;
    protected $maskingKey = 0;
    protected $payloadData = '';
    protected $actualLength = 0;

    private function __construct()
    {

    }

    /**
     * @param int $type
     * @param null $data
     * @return WebSocketFrame
     */
    public static function create($type, $data = null)
    {
        $o = new self();

        $o->FIN = true;
        $o->payloadData = $data;
        $o->payloadLength = $data != null ? strlen($data) : 0;
        $o->setType($type);

        return $o;
    }

    /**
     * @param bool $mask
     */
    public function setMasked($mask)
    {
        $this->mask = $mask ? 1 : 0;
    }

    /**
     * @return bool
     */
    public function isMasked()
    {
        return $this->mask == 1;
    }

    /**
     * @param int $type
     */
    protected function setType($type)
    {
        $this->opcode = $type;

        if ($type == WebSocketOpcode::CLOSE_FRAME) {
            $this->mask = 1;
        }
    }

    /**
     * @param int $byte
     * @param int $pos
     * @return int
     */
    protected static function isBitSet($byte, $pos)
    {
        return ($byte & pow(2, $pos)) > 0 ? 1 : 0;
    }

    /**
     * @param $data
     * @param $key
     * @param int $offset
     * @return int
     */
    protected static function rotMask($data, $key, $offset = 0)
    {
        // Rotate key for example if $offset=1 and $key=abcd then output will be bcda
        $rotatedKey = substr($key, $offset) . substr($key, 0, $offset);

        // Repeat key until it is at least the size of the $data
        $keyPad = str_repeat($rotatedKey, ceil(1.0*strlen($data) / strlen($key)));

        return $data ^ substr($keyPad, 0, strlen($data));
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->opcode;
    }

    /**
     * @return string
     */
    public function encode()
    {
        $this->payloadLength = strlen($this->payloadData);

        $firstByte = $this->opcode;

        $firstByte += $this->FIN * 128 + $this->RSV1 * 64 + $this->RSV2 * 32 + $this->RSV3 * 16;

        $encoded = chr($firstByte);

        if ($this->payloadLength <= 125) {
            $secondByte = $this->payloadLength;
            $secondByte += $this->mask * 128;

            $encoded .= chr($secondByte);
        } elseif ($this->payloadLength <= 256 * 256 - 1) {
            $secondByte = 126;
            $secondByte += $this->mask * 128;

            $encoded .= chr($secondByte) . pack("n", $this->payloadLength);
        } else {
            // TODO: max length is now 32 bits instead of 64 !!!!!
            $secondByte = 127;
            $secondByte += $this->mask * 128;

            $encoded .= chr($secondByte);
            $encoded .= pack("N", 0);
            $encoded .= pack("N", $this->payloadLength);
        }

        $key = 0;
        if ($this->mask) {
            $key = pack("N", rand(0, pow(255, 4) - 1));
            $encoded .= $key;
        }

        if ($this->payloadData) {
            $encoded .= ($this->mask == 1)
                ? $this->rotMask($this->payloadData, $key)
                : $this->payloadData;
        }

        return $encoded;
    }

    /**
     * @param $buffer
     * @return WebSocketFrame|null
     */
    public static function decode(&$buffer)
    {
        if (strlen($buffer) < 2) {
            return null;
        }

        $frame = new self();

        // Read the first two bytes, then chop them off
        list($firstByte, $secondByte) = substr($buffer, 0, 2);
        $raw = substr($buffer, 2);

        $firstByte = ord($firstByte);
        $secondByte = ord($secondByte);

        $frame->FIN = self::isBitSet($firstByte, 7);
        $frame->RSV1 = self::isBitSet($firstByte, 6);
        $frame->RSV2 = self::isBitSet($firstByte, 5);
        $frame->RSV3 = self::isBitSet($firstByte, 4);

        $frame->mask = self::isBitSet($secondByte, 7);

        $frame->opcode = ($firstByte & 0x0F);

        $len = $secondByte & ~128;

        if ($len <= 125) {
            $frame->payloadLength = $len;
        } elseif (($len == 126) && strlen($raw) >= 2) {
            $arr = unpack("nfirst", $raw);
            $frame->payloadLength = array_pop($arr);
            $raw = substr($raw, 2);
        } elseif (($len == 127) && strlen($raw) >= 8) {
            list(, $h, $l) = unpack('N2', $raw);
            $frame->payloadLength = ($l + ($h * 0x0100000000));
            $raw = substr($raw, 8);
        } else {
            return null;
        }

        // If the frame is masked, try to eat the key from the buffer. If the buffer is insufficient, return null and
        // try again next time
        if ($frame->mask) {
            if (strlen($raw) < 4) {
                return null;
            }

            $frame->maskingKey = substr($raw, 0, 4);
            $raw = substr($raw, 4);
        }

        // Don't continue until we have a full frame
        if (strlen($raw) < $frame->payloadLength) {
            return null;
        }

        $packetPayload = substr($raw, 0, $frame->payloadLength);

        // Advance buffer
        $buffer = substr($raw, $frame->payloadLength);

        if ($frame->mask) {
            $frame->payloadData = self::rotMask($packetPayload, $frame->maskingKey, 0);
        } else {
            $frame->payloadData = $packetPayload;
        }

        return $frame;
    }

    /**
     * @return bool
     * @throws WebSocketFrameSizeMismatch
     */
    public function isReady()
    {
        if ($this->actualLength > $this->payloadLength) {
            throw new WebSocketFrameSizeMismatch($this);
        }

        return ($this->actualLength == $this->payloadLength);
    }

    /**
     * @return bool
     */
    public function isFinal()
    {
        return $this->FIN == 1;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->payloadData;
    }
}
