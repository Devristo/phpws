<?php

namespace Devristo\Phpws\Exceptions;

use Exception;

class WebSocketInvalidKeyException extends Exception
{

    public function __construct($key1, $key2, $l8b)
    {
        parent::__construct("Client sent an invalid opening handshake!");
        fwrite(STDERR, "Key 1: \t$key1\nKey 2: \t$key2\nL8b: \t$l8b");
    }

}