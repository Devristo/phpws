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

class Handshake
{
    protected $abort = false;
    protected $request;
    protected $response;

    /**
     * @param Request $request
     * @param Response $response
     */
    public function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return Response
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * @return void
     */
    public function abort()
    {
        $this->abort = true;
    }

    /**
     * @return bool
     */
    public function isAborted()
    {
        return $this->abort;
    }
}
