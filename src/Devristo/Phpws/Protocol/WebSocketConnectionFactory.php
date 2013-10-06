<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:43 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Protocol;

use Devristo\Phpws\Protocol\WebSocketConnectionFlash;
use Devristo\Phpws\Protocol\WebSocketConnectionHixie;
use Devristo\Phpws\Protocol\WebSocketConnectionHybi;
use Devristo\Phpws\Protocol\WebSocketConnectionRole;
use Devristo\Phpws\Protocol\WebSocketStream;

class WebSocketConnectionFactory
{

    public static function fromSocketData(WebSocketStream $socket, $data)
    {
        $headers = self::parseHeaders($data);

        if (isset($headers['Sec-Websocket-Key1'])) {
            $s = new WebSocketConnectionHixie($socket, $headers, $data);
            $s->sendHandshakeResponse();
        } else if (strpos($data, '<policy-file-request/>') === 0) {
            $s = new WebSocketConnectionFlash($socket, $data);
        } else {
            $s = new WebSocketConnectionHybi($socket, $headers);
            $s->sendHandshakeResponse();
        }

        $s->setRole(WebSocketConnectionRole::SERVER);


        return $s;
    }

    /**
     * Parse HTTP request into an array
     *
     * @param string $header HTTP request as a string
     * @return array Headers as a key-value pair array
     */
    public static function parseHeaders($header)
    {
        $retVal = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        foreach ($fields as $field) {
            if (preg_match('/([^:]+): (.+)/m', $field, $match)) {
                $match[1] = preg_replace_callback('/(?<=^|[\x09\x20\x2D])./', function ($m) {
                    return strtoupper($m[0]);
                }, strtolower(trim($match[1])));
                if (isset($retVal[$match[1]])) {
                    $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                } else {
                    $retVal[$match[1]] = trim($match[2]);
                }
            }
        }

        if (preg_match("/GET (.*) HTTP/", $header, $match)) {
            $retVal['GET'] = $match[1];
        }

        return $retVal;
    }

}