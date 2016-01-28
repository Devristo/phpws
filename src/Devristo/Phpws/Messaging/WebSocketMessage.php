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
    protected $frames = [];
    protected $data = '';

    /**
     * @param string $data
     */
    public function setData($data)
    {
        $this->data = $data;

        $this->createFrames();
    }

    /**
     * @param string $data
     * @return WebSocketMessage
     */
    public static function create($data)
    {
        $o = new self();

        $o->setData($data);
        return $o;
    }

    /**
     * @return string
     * @throws WebSocketMessageNotFinalised
     */
    public function getData()
    {
        if ($this->isFinalised() == false) {
            throw new WebSocketMessageNotFinalised($this);
        }

        $data = '';

        foreach ($this->frames as $frame) {
            $data .= $frame->getData();
        }

        return $data;
    }

    /**
     * @param WebSocketFrameInterface $frame
     * @return WebSocketMessage
     */
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
        $this->frames = [
            WebSocketFrame::create(WebSocketOpcode::TEXT_FRAME, $this->data)
        ];
    }

    /**
     * @return \Devristo\Phpws\Framing\WebSocketFrame[]
     */
    public function getFrames()
    {
        return $this->frames;
    }

    /**
     * @return bool
     */
    public function isFinalised()
    {
        if (count($this->frames) == 0) {
            return false;
        }

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
