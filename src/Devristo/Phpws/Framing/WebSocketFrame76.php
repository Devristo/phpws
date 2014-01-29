<?php
namespace Devristo\Phpws\Framing;


class WebSocketFrame76 implements WebSocketFrameInterface
{

    public $payloadData = '';
    protected $opcode = WebSocketOpcode::TextFrame;

    public static function create($type, $data = null)
    {
        $o = new self();

        $o->payloadData = $data;

        return $o;
    }

    public function encode()
    {
        return chr(0) . $this->payloadData . chr(255);
    }

    public function getData()
    {
        return $this->payloadData;
    }

    public function getType()
    {
        return $this->opcode;
    }

    public static function decode(&$str)
    {
        $o = new self();
        $o->payloadData = substr($str, 1, strlen($str) - 2);

        $str = '';

        return $o;
    }

}
