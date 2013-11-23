<?php
/**
 * Created by PhpStorm.
 * User: Chris
 * Date: 21-11-13
 * Time: 18:06
 */

namespace Devristo\Phpws\Server;


use Zend\Log\Logger;

class SocketServer {
    /**
     * @var \SplObjectStorage|ISocketStream[]
     */
    protected $streams;

    /**
     * @var \Zend\Log\LoggerInterface
     */
    protected $_logger = null;


    public function __construct($logger = null){

        $this->streams = new \SplObjectStorage();

        $this->_logger = $logger;
    }

    public function attachStream(ISocketStream $stream){
        $this->streams->attach($stream);
        $this->_logger->info("Attaching stream");
    }

    public function detachStream(ISocketStream $stream){
        $this->streams->detach($stream);
        $this->_logger->info("Detaching stream");
    }

    public function run(){
        $this->_eventLoop();
    }

    public function getStreams(){
        return $this->streams;
    }

    private function detachClosedStreams(){
        $closed = array();
        foreach($this->streams as $stream)
            if($stream->isClosed())
                $closed[] = $stream;

        foreach($closed as $stream)
            $this->detachStream($stream);
    }

    public function getSockets(){
        $sockets = array();

        foreach($this->streams as $stream)
            $sockets[] = $stream->getSocket();

        return $sockets;
    }

    private function getWriteResources(){
        $wantsToWrite = array();
        foreach($this->getStreams() as $stream){
            if($stream->requestsWrite())
                $wantsToWrite[] = $stream->getSocket();
        }

        return $wantsToWrite;
    }

    private function getStreamByResource($resource){
        foreach($this->streams as $stream)
            if($stream->getSocket() == $resource)
                return $stream;

        return null;
    }

    protected function _eventLoop(){
        while (true) {
            clearstatcache();
            gc_collect_cycles();

            $changed = $this->getSockets();
            $write = $this->getWriteResources();
            $except = null;

            if (@stream_select($changed, $write, $except, null) === false) {
                $this->_logger->err("Stream select has failed. You might need to restart the server if it happens again");
                break;
            }
            $this->_logger->debug("Streams selected");


            foreach ($changed as $resource) {
                $stream = $this->getStreamByResource($resource);

                if(!$stream){
                    $this->_logger->err("Cannot find ISocketStream using this socket, skipping it");
                    @fclose($resource);
                    continue;
                }


                if ($stream->isServer()) {
                    $stream->acceptConnection();
                } else {
                    $buffer = @fread($resource, 8192);

                    // If read returns false, close the stream and continue with the next socket
                    if ($buffer === false) {
                        $stream->close();
                        $this->detachStream($stream);
                        // Skip to next stream
                        continue;
                    }

                    $bytes = strlen($buffer);

                    if ($bytes === 0) {
                        $stream->close();
                        $this->detachStream($stream);
                    } else if ($stream != null) {
                        $stream->onData($buffer);
                    }
                }
            }

            if (is_array($write)) {
                foreach ($write as $resource) {
                    $stream = $this->getStreamByResource($resource);

                    if(!$stream){
                        $this->_logger->err("Cannot find ISocketStream using this socket, skipping it");
                        @fclose($resource);
                        continue;
                    }

                    $stream->mayWrite();
                }
            }
        }
    }
} 