<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 28-11-13
 * Time: 19:17
 */

namespace Devristo\Phpws\Protocol;


use Zend\Http\Request;
use Zend\Http\Response;

class Handshake {
    protected $abort = false;
    protected $request;
    protected $response;

    public function __construct(Request $request, Response $response){
        $this->request = $request;
        $this->response = $response;
    }

    public function getRequest(){
        return $this->request;
    }

    public function getResponse(){
        return $this->response;
    }

    public function abort(){
        $this->abort = true;
    }

    public function isAborted(){
        return $this->abort;
    }
} 