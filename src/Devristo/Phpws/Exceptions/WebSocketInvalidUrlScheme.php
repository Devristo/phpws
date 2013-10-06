<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:39 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Exceptions;

use Exception;

class WebSocketInvalidUrlScheme extends Exception
{

    public function __construct()
    {
        parent::__construct("Only 'ws://' urls are supported!");
    }

}