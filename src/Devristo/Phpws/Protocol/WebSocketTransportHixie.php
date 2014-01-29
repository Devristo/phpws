<?php

namespace Devristo\Phpws\Protocol;

use Devristo\Phpws\Exceptions\WebSocketInvalidKeyException;
use Devristo\Phpws\Framing\WebSocketFrame76;
use Devristo\Phpws\Messaging\WebSocketMessage76;
use React\Stream\WritableStreamInterface;
use Zend\Http\Headers;
use Zend\Http\Request;
use Zend\Http\Response;

class WebSocketTransportHixie extends WebSocketTransport
{

    public function respondTo(Request $request){
        $this->request = $request;
        $this->sendHandshakeResponse();
    }

    private function sendHandshakeResponse()
    {
        // Last 8 bytes of the client's handshake are used for key calculation later
        $l8b = $this->request->getContent();

        // Check for 2-key based handshake (Hixie protocol draft)
        $key1 = $this->getHandshakeRequest()->getHeader('Sec-Websocket-Key1')->getFieldValue();
        $key2 = $this->getHandshakeRequest()->getHeader('Sec-Websocket-Key2')->getFieldValue();

        // Origin checking (TODO)
        $originHeader  = $this->getHandshakeRequest()->getHeader('Origin', null);
        $host = $this->getHandshakeRequest()->getHeader('Host')->getFieldValue();
        $location = $this->getHandshakeRequest()->getUriString();

        // Build response
        $response = new Response();
        $response->setStatusCode(101);
        $response->setReasonPhrase("WebSocket Protocol Handshake");

        $headers = new Headers();
        $response->setHeaders($headers);

        $headers->addHeaderLine("Upgrade", "WebSocket");
        $headers->addHeaderLine("Connection", "Upgrade");

        if($originHeader)
            $headers->addHeaderLine("Sec-WebSocket-Origin", $originHeader->getFieldValue());
        $headers->addHeaderLine("Sec-WebSocket-Location", "ws://{$host}$location");

        // Build HIXIE response
        $response->setContent(self::calcHixieResponse($key1, $key2, $l8b));

        $this->setResponse($response);

        $handshakeRequest = new Handshake($this->getHandshakeRequest(), $this->getHandshakeResponse());
        $this->emit("handshake", array($handshakeRequest));

        if($handshakeRequest->isAborted())
            $this->close();
        else {
            $this->_socket->write($response->toString());
            $this->logger->debug("Got an HYBI style request, sent HYBY handshake response");

            $this->emit("connect");
        }
    }

    /**
     * Calculate the #76 draft key based on the 2 challenges from the client and the last 8 bytes of the request
     *
     * @param string $key1 Sec-WebSocket-Key1
     * @param string $key2 Sec-Websocket-Key2
     * @param string $l8b Last 8 bytes of the client's opening handshake
     *
     * @throws \Devristo\Phpws\Exceptions\WebSocketInvalidKeyException
     * @return string Hixie compatible response to client's challenge
     */
    private static function calcHixieResponse($key1, $key2, $l8b)
    {
        // Get the numbers from the opening handshake
        $numbers1 = preg_replace("/[^0-9]/", "", $key1);
        $numbers2 = preg_replace("/[^0-9]/", "", $key2);

        //Count spaces
        $spaces1 = substr_count($key1, " ");
        $spaces2 = substr_count($key2, " ");

        if ($spaces1 == 0 || $spaces2 == 0) {
            throw new WebSocketInvalidKeyException($key1, $key2, $l8b);
        }

        // Key is the number divided by the amount of spaces expressed as a big-endian 32 bit integer
        $key1_sec = pack("N", $numbers1 / $spaces1);
        $key2_sec = pack("N", $numbers2 / $spaces2);

        // The response is the md5-hash of the 2 keys and the last 8 bytes of the opening handshake, expressed as a binary string
        return md5($key1_sec . $key2_sec . $l8b, 1);
    }


    public function handleData(&$data)
    {
        $f = WebSocketFrame76::decode($data);
        $message = WebSocketMessage76::fromFrame($f);

        $this->emit("message", array('message' => $message));

        return array($f);
    }

    public function sendString($msg)
    {
        $m = WebSocketMessage76::create($msg);

        return $this->sendMessage($m);
    }

    public function close()
    {
        $this->_socket->close();
    }
}
