<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:44 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Protocol;

use Devristo\Phpws\Framing\WebSocketFrameInterface;
use Devristo\Phpws\Messaging\WebSocketMessageInterface;
use Evenement\EventEmitter;
use React\Stream\WritableStreamInterface;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;

abstract class WebSocketTransport extends EventEmitter implements WebSocketTransportInterface, LoggerAwareInterface
{
    public $parameters;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     *
     * @var WebSocketConnection
     */
    protected $socket;
    protected $cookies = [];
    protected $role = WebsocketTransportRole::CLIENT;

    protected $eventManger;

    protected $data = [];

    protected $id;

    /**
     * @param WritableStreamInterface $socket
     */
    public function __construct(WritableStreamInterface $socket)
    {
        $this->socket = $socket;
        $this->id = uniqid("connection-");

        $that = $this;

        $buffer = '';

        $socket->on("data", function ($data) use ($that, &$buffer) {
            $buffer .= $data;
            $that->handleData($buffer);
        });

        $socket->on("close", function ($data) use ($that) {
            $that->emit("close", func_get_args());
        });
    }

    /**
     * @return string
     */
    public function getIp()
    {
        return $this->socket->getRemoteAddress();
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    protected function setRequest(Request $request)
    {
        $this->request = $request;
    }

    protected function setResponse(Response $response)
    {
        $this->response = $response;
    }

    /**
     * @return Request
     */
    public function getHandshakeRequest()
    {
        return $this->request;
    }

    /**
     * @return Response
     */
    public function getHandshakeResponse()
    {
        return $this->response;
    }

    /**
     * @return WebSocketConnection|WritableStreamInterface
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param WebSocketFrameInterface $frame
     * @return bool
     */
    public function sendFrame(WebSocketFrameInterface $frame)
    {
        if ($this->socket->write($frame->encode()) === false) {
            return false;
        }

        return true;
    }

    /**
     * @param WebSocketMessageInterface $msg
     * @return bool
     */
    public function sendMessage(WebSocketMessageInterface $msg)
    {
        foreach ($msg->getFrames() as $frame) {
            if ($this->sendFrame($frame) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $key
     * @param $value
     */
    public function setData($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getData($key)
    {
        return $this->data[$key];
    }
}
