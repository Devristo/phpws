<?php
namespace Devristo\Phpws\RemoteEvent;

use Devristo\Phpws\Messaging\RemoteEventMessage;
use Devristo\Phpws\Protocol\StackTransport;

class Room extends \Evenement\EventEmitter
{
    private $members = array();
    private $name = '';
    private $logger;

    public function __construct($name, \Zend\Log\LoggerInterface $logger)
    {
        $this->name = $name;
        $this->logger = $logger;
    }

    public function subscribe(StackTransport $transport)
    {
        $this->members[$transport->getId()] = $transport;
        $this->logger->notice("[{$this->name}] User {$transport->getId()} has subscribed!");
    }

    public function unsubscribe(StackTransport $transport)
    {
        if (array_key_exists($transport->getId(), $this->members)){
            unset($this->members[$transport->getId()]);

            $this->emit("unsubscribe", array($transport));
        }
    }

    /**
     * @return StackTransport[]
     */
    public function getMembers()
    {
        return array_values($this->members);
    }

    public function remoteEmit($event, $data){
        foreach($this->getMembers() as $member){
            $message = RemoteEventMessage::create($this->name, $event, $data);
            $member->getTopTransport()->send($message);
        }
    }
}