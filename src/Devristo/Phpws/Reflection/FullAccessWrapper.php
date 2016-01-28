<?php
/**
 * User: martin dot partel at gmail dot com
 * http://us3.php.net/manual/en/functions.anonymous.php#98384
 */

namespace Devristo\Phpws\Reflection;

class FullAccessWrapper
{
    protected $self;
    protected $refl;

    public function __construct($self)
    {
        $this->self = $self;
        $this->refl = new \ReflectionObject($self);
    }

    public function __call($method, $args)
    {
        $mrefl = $this->refl->getMethod($method);
        $mrefl->setAccessible(true);
        return $mrefl->invokeArgs($this->self, $args);
    }

    public function __set($name, $value)
    {
        $prefl = $this->refl->getProperty($name);
        $prefl->setAccessible(true);
        $prefl->setValue($this->self, $value);
    }

    public function __get($name)
    {
        $prefl = $this->refl->getProperty($name);
        $prefl->setAccessible(true);
        return $prefl->getValue($this->self);
    }

    public function __isset($name)
    {
        $value = $this->__get($name);
        return isset($value);
    }
}
