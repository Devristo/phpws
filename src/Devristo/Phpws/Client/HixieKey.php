<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 6:33 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Client;
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 6:21 PM
 * To change this template use File | Settings | File Templates.
 */
class HixieKey
{

    public $number;
    public $key;

    public function __construct($number, $key)
    {
        $this->number = $number;
        $this->key = $key;
    }

}