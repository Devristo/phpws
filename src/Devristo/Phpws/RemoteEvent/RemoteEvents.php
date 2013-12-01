<?php

namespace Devristo\Phpws\RemoteEvent;

use Devristo\Phpws\Messaging\RemoteEventMessage;
use Devristo\Phpws\Protocol\StackTransport;
use Devristo\Phpws\RemoteEvent\Room;

class RemoteEvents extends \Evenement\EventEmitter
{
    /**
     * @var Room[]
     */
    protected $rooms = array();
    protected $logger;

    public function __construct(\Zend\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param $room
     * @return Room
     */
    public function room($room)
    {
        if (!array_key_exists($room, $this->rooms))
            $this->rooms[$room] = new Room($room, $this->logger);

        return $this->rooms[$room];
    }

    public function listenTo(StackTransport $transport)
    {
        $self = $this;
        $transport->on("message", function (RemoteEventMessage $message) use ($transport, $self) {
            $room = $message->getRoom();

            if (!$room)
                return;

            $event = $message->getEvent();

            if ($message->getEvent() == 'subscribe')
                $self->room($room)->subscribe($transport);
            elseif ($message->getEvent() == 'unsubscribe')
                $self->room($room)->unsubscribe($transport);

            $self->room($room)->emit($event, array($transport, $message));
            $self->emit($event, array($transport, $message));
        });
    }

    public function getRooms()
    {
        return array_keys($this->rooms);
    }
}