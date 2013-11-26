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
use Devristo\Phpws\Framing\WebSocketFrame;
use Devristo\Phpws\Framing\WebSocketOpcode;
use Devristo\Phpws\Messaging\WebSocketMessage;
use Exception;

class WebSocketConnectionHybi extends WebSocketConnection
{

    /**
     * @var WebSocketMessage
     */
    private $_openMessage = null;

    /**
     * @var WebSocketFrame
     */
    private $lastFrame = null;

    public function sendHandshakeResponse()
    {
        // Check for newer handshake
        $challenge = isset($this->_headers['Sec-Websocket-Key']) ? $this->_headers['Sec-Websocket-Key'] : null;

        // Build response
        $response = "HTTP/1.1 101 WebSocket Protocol Handshake\r\n" . "Upgrade: WebSocket\r\n" . "Connection: Upgrade\r\n";

        // Build HYBI response
        $response .= "Sec-WebSocket-Accept: " . self::calcHybiResponse($challenge) . "\r\n\r\n";

        $this->_socket->write($response);

        $this->logger->debug("Got an HYBI style request, sent HYBY handshake response");
    }

    private static function calcHybiResponse($challenge)
    {
        return base64_encode(sha1($challenge . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    }

    public function onData($data)
    {
        $frames = array();
        while (!empty($data)) {
            $frame = WebSocketFrame::decode($data, $this->lastFrame);
            if ($frame->isReady()) {

                if (WebSocketOpcode::isControlFrame($frame->getType()))
                    $this->processControlFrame($frame);
                else
                    $this->processMessageFrame($frame);

                $this->lastFrame = null;
            } else {
                $this->lastFrame = $frame;
            }

            $frames[] = $frame;
        }

        return $frames;
    }

    public function sendFrame(WebSocketFrameInterface $frame)
    {
        /**
         * @var $hybiFrame WebSocketFrame
         */
        $hybiFrame = $frame;

        // Mask IFF client!
        $hybiFrame->setMasked($this->_role == WebSocketConnectionRole::CLIENT);

        parent::sendFrame($hybiFrame);
    }

    /**
     * Process a Message Frame
     *
     * Appends or creates a new message and attaches it to the user sending it.
     *
     * When the last frame of a message is received, the message is sent for processing to the
     * abstract WebSocket::onMessage() method.
     *
     * @param WebSocketFrame $frame
     */
    protected function processMessageFrame(WebSocketFrame $frame)
    {
        if ($this->_openMessage && $this->_openMessage->isFinalised() == false) {
            $this->_openMessage->takeFrame($frame);
        } else {
            $this->_openMessage = WebSocketMessage::fromFrame($frame);
        }

        if ($this->_openMessage && $this->_openMessage->isFinalised()) {
            $this->emit("message", array('message' => $this->_openMessage));
            $this->_openMessage = null;
        }
    }

    /**
     * Handle incoming control frames
     *
     * Sends Pong on Ping and closes the connection after a Close request.
     *
     * @param WebSocketFrame $frame
     */
    protected function processControlFrame(WebSocketFrame $frame)
    {
        switch ($frame->getType()) {
            case WebSocketOpcode::CloseFrame :
                $this->logger->notice("Got CLOSE frame");

                $frame = WebSocketFrame::create(WebSocketOpcode::CloseFrame);
                $this->sendFrame($frame);

                $this->_socket->close();
                break;
            case WebSocketOpcode::PingFrame :
                $frame = WebSocketFrame::create(WebSocketOpcode::PongFrame);
                $this->sendFrame($frame);
                break;
        }
    }

    public function sendString($msg)
    {
        try {
            $m = WebSocketMessage::create($msg);

            return $this->sendMessage($m);
        } catch (Exception $e) {
            $this->close();
        }

        return false;
    }

    public function close()
    {
        $f = WebSocketFrame::create(WebSocketOpcode::CloseFrame);
        $this->sendFrame($f);

        $this->_socket->close();
    }

}