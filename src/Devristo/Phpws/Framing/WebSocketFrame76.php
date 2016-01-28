<?php
namespace Devristo\Phpws\Framing;

class WebSocketFrame76 implements WebSocketFrameInterface
{
    public $payloadData = '';
    protected $opcode = WebSocketOpcode::TEXT_FRAME;

    /**
     * @param int $type
     * @param null $data
     * @return WebSocketFrame76
     */
    public static function create($type, $data = null)
    {
        $o = new self();

        $o->payloadData = $data;

        return $o;
    }

    /**
     * @return string
     */
    public function encode()
    {
        return chr(0) . $this->payloadData . chr(255);
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->payloadData;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->opcode;
    }

    /**
     * @param $str
     * @return WebSocketFrame76
     */
    public static function decode(&$str)
    {
        $o = new self();
        $o->payloadData = substr($str, 1, strlen($str) - 2);

        $str = '';

        return $o;
    }
}
