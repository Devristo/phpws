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
use Zend\Log\LoggerAwareInterface;
use Zend\Log\LoggerInterface;

abstract class WebSocketTransport extends EventEmitter implements TransportInterface, WebSocketConnectionInterface, LoggerAwareInterface
{

    protected $_headers = array();

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     *
     * @var WebSocketServerClient
     */
    protected $_socket = null;
    protected $_cookies = array();
    public $parameters = null;
    protected $_role = WebSocketConnectionRole::CLIENT;

    protected $_eventManger;

    public function __construct(WritableStreamInterface $socket, array $headers)
    {
        $this->setHeaders($headers);
        $this->_socket = $socket;
        $this->_id = uniqid("connection-");
    }

    public function getIp()
    {
        return $this->_socket->getRemoteAddress();
    }

    public function getId()
    {
        return $this->_id;
    }

    public function sendFrame(WebSocketFrameInterface $frame)
    {
        if ($this->_socket->write($frame->encode()) === false)
            return false;

        return true;
    }

    public function sendMessage(WebSocketMessageInterface $msg)
    {
        foreach ($msg->getFrames() as $frame) {
            if ($this->sendFrame($frame) === false)
                return false;
        }

        return true;
    }

    public function getHeaders()
    {
        return $this->_headers;
    }

    public function setHeaders($headers)
    {
        $this->_headers = $headers;

        if (array_key_exists('Cookie', $this->_headers) && is_array($this->_headers['Cookie'])) {
            $this->_cookies = array();
        } else {
            if (array_key_exists("Cookie", $this->_headers)) {
                $this->_cookies = self::cookie_parse($this->_headers['Cookie']);
            } else
                $this->_cookies = array();
        }

        $this->getQueryParts();
    }

    /**
     * Parse a HTTP HEADER 'Cookie:' value into a key-value pair array
     *
     * @param string $line Value of the COOKIE header
     * @return array Key-value pair array
     */
    private static function cookie_parse($line)
    {
        $cookies = array();
        $csplit = explode(';', $line);

        foreach ($csplit as $data) {

            $cinfo = explode('=', $data);
            $key = trim($cinfo[0]);
            $val = urldecode($cinfo[1]);

            $cookies[$key] = $val;
        }

        return $cookies;
    }

    public function getCookies()
    {
        return $this->_cookies;
    }

    public function getUriRequested()
    {
        if (array_key_exists('GET', $this->_headers))
            return $this->_headers['GET'];
        else
            return null;
    }

    public function setRole($role)
    {
        $this->_role = $role;
    }

    protected function getQueryParts()
    {
        $url = $this->getUriRequested();

        // We dont have an URL to process (this is the case for the client)
        if ($url == null)
            return;

        if (($pos = strpos($url, "?")) == -1) {
            $this->parameters = array();
        }

        $q = substr($url, strpos($url, "?") + 1);

        $keyValuePairs = explode("&", $q);
        $this->parameters = array();

        foreach ($keyValuePairs as $kv) {
            if (strpos($kv, "=") == -1)
                continue;

            @list($k, $v) = explode("=", $kv);

            $this->parameters[urldecode($k)] = urldecode($v);
        }
    }

    public function getSocket()
    {
        return $this->_socket;
    }

    public function setLogger(LoggerInterface $logger){
        $this->logger = $logger;
    }

    public function setCarrier(TransportInterface $carrierProtocol)
    {

    }
}