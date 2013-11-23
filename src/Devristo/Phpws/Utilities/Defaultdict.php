<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 23-11-13
 * Time: 12:27
 */

namespace Devristo\Phpws\Utilities;


class DefaultDict implements \ArrayAccess{
    /**
     * @var callable|mixed|\Closure
     */
    private $default;
    private $dict = array();

    public function __construct($default){
        $this->default = $default;
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
        return true;
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
    public function &offsetGet($offset)
    {
        if(!array_key_exists($offset, $this->dict)){
            $value = is_callable($this->default) ? call_user_func($this->default, $offset) : $this->default;
            $this->dict[$offset] = $value;
        }

        return $this->dict[$offset];
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
     * @throws \InvalidArgumentException
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        if(!isset($offset)){
            throw new \InvalidArgumentException("No key specified for dictionary");
        }

        $this->dict[$offset] = $value;
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
        unset($this->dict[$offset]);
    }
}