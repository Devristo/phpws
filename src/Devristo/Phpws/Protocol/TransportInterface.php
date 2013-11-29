<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 27-11-13
 * Time: 18:45
 */

namespace Devristo\Phpws\Protocol;


use Evenement\EventEmitterInterface;

interface TransportInterface extends EventEmitterInterface{
    public function sendString($string);
} 