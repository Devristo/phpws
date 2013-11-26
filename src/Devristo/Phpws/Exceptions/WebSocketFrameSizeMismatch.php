<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:39 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Exceptions;

use Devristo\Phpws\Framing\WebSocketFrameInterface;
use Exception;

class WebSocketFrameSizeMismatch extends Exception
{

    public function __construct(WebSocketFrameInterface $msg)
    {
        parent::__construct("Frame size mismatches with the expected frame size. Maybe a buggy client.");
    }

}