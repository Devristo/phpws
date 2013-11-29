<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 28-11-13
 * Time: 19:42
 */

namespace Devristo\Phpws\Protocol;


use Zend\Http\Request;
use Zend\Http\Response;

class StackTransport implements \ArrayAccess, WebSocketTransportInterface{
    protected $stack;

    public function __construct(array &$stack){
        if(count($stack) < 1)
            throw new \InvalidArgumentException("Stack must be a non-empty array");

        $this->stack = $stack;
    }

    /**
     * @return WebSocketTransportInterface
     */
    public function getWebSocketTransport(){
        return $this->stack[0];
    }

    /**
     * @return TransportInterface
     */
    public function getTopTransport(){
        return $this->stack[count($this->stack) - 1];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return $offset < count($this->stack);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return $this->stack[$offset];
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException("Immutable stack, cannot set element");
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException("Immutable stack, cannot set element");
    }

    public function on($event, $listener)
    {
        return $this->getWebSocketTransport()->on($event, $listener);
    }

    public function once($event, $listener)
    {
        return $this->getWebSocketTransport()->once($event, $listener);
    }

    public function removeListener($event, $listener)
    {
        return $this->getWebSocketTransport()->removeListener($event, $listener);
    }

    public function removeAllListeners($event = null)
    {
        return $this->getWebSocketTransport()->removeAllListeners($event);
    }

    public function listeners($event)
    {
        return $this->getWebSocketTransport()->listeners($event);
    }

    public function emit($event, array $arguments = array())
    {
        return $this->getWebSocketTransport()->emit($event, $arguments);
    }

    public function getId()
    {
        return $this->getWebSocketTransport()->getId();
    }

    public function respondTo(Request $request)
    {
        throw new \BadMethodCallException();
    }

    public function handleData($data)
    {
        throw new \BadMethodCallException();
    }

    public function sendString($msg)
    {
        $this->getTopTransport()->sendString($msg);
    }

    public function getIp()
    {
        $this->getWebSocketTransport()->getIp();
    }

    public function close()
    {
        $this->getWebSocketTransport()->close();
    }

    /**
     * @return Request
     */
    public function getHandshakeRequest()
    {
        return $this->getWebSocketTransport()->getHandshakeRequest();
    }

    /**
     * @return Response
     */
    public function getHandshakeResponse()
    {
        return $this->getWebSocketTransport()->getHandshakeResponse();
    }
}