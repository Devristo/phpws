<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:39 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Exceptions;

use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Exception;

class WebSocketMessageNotFinalised extends Exception
{

    public function __construct(WebSocketMessageInterface $msg)
    {
        parent::__construct("WebSocketMessage is not finalised!");
    }

}