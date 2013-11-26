<?php
namespace Devristo\Phpws\Messaging;
use Devristo\Phpws\Exceptions\WebSocketMessageNotFinalised;
use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketFrameInterface;
use Devristo\Phpws\Framing\WebSocketOpcode;


/**
 * WebSocketMessage compatible with the latest draft.
 * Should be updated to keep up with the latest changes.
 *
 * @author Chris
 *
 */
class WebSocketMessage implements WebSocketMessageInterface
{

    /**
     *
     * Enter description here ...
     * @var \Devristo\Phpws\Framing\WebSocketFrame[];
     */
    protected $frames = array();
    protected $data = '';

    public function setData($data)
    {
        $this->data = $data;

        $this->createFrames();
    }

    public static function create($data)
    {
        $o = new self();

        $o->setData($data);
        return $o;
    }

    public function getData()
    {
        if ($this->isFinalised() == false)
            throw new WebSocketMessageNotFinalised($this);

        $data = '';

        foreach ($this->frames as $frame) {
            $data .= $frame->getData();
        }

        return $data;
    }

    public static function fromFrame(WebSocketFrameInterface $frame)
    {
        assert($frame instanceof WebSocketFrame);

        /** @var $frame \Devristo\Phpws\Framing\WebSocketFrame */

        $o = new self();
        $o->takeFrame($frame);

        return $o;
    }

    protected function createFrames()
    {
        $this->frames = array(WebSocketFrame::create(WebSocketOpcode::TextFrame, $this->data));
    }

    public function getFrames()
    {
        return $this->frames;
    }

    public function isFinalised()
    {
        if (count($this->frames) == 0)
            return false;

        return $this->frames[count($this->frames) - 1]->isFinal();
    }

    /**
     * Append a frame to the message
     * @param \Devristo\Phpws\Framing\WebSocketFrame $frame
     */
    public function takeFrame(WebSocketFrame $frame)
    {
        $this->frames[] = $frame;
    }

}