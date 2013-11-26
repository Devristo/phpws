<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:43 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Messaging;
use Devristo\Phpws\Framing\WebSocketFrameInterface;
use Devristo\Phpws\Framing\WebSocketFrame76;
use Devristo\Phpws\Framing\WebSocketOpcode;

/**
 * WebSocketMessage compatible with the Hixie Draft #76
 * Used for backwards compatibility with older versions of Chrome and
 * several Flash fallback solutions
 *
 * @author Chris
 */
class WebSocketMessage76 implements WebSocketMessageInterface
{

    protected $data = '';

    /**
     * @var WebSocketFrame76
     */
    protected $frame = null;

    public static function create($data)
    {
        $o = new self();

        $o->setData($data);
        return $o;
    }

    public function getFrames()
    {
        $arr = array();

        $arr[] = $this->frame;

        return $arr;
    }

    public function setData($data)
    {
        $this->data = $data;
        $this->frame = WebSocketFrame76::create(WebSocketOpcode::TextFrame, $data);
    }

    public function getData()
    {
        return $this->frame->getData();
    }

    public function isFinalised()
    {
        return true;
    }

    /**
     * Creates a new WebSocketMessage76 from a IWebSocketFrame
     * @param WebSocketFrameInterface $frame
     *
     * @return \Devristo\Phpws\Messaging\WebSocketMessage76 Message composed of the frame provided
     */
    public static function fromFrame(WebSocketFrameInterface $frame)
    {
        $o = new self();
        $o->frame = $frame;

        return $o;
    }

}