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
    public function getId();
    public function onData($string);
    public function sendString($string);
    public function setCarrier(TransportInterface $carrierProtocol);
} 