<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:46 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Server\UriHandler;

use Devristo\Phpws\Protocol\WebSocketTransportInterface;
use Evenement\EventEmitterInterface;

interface WebSocketUriHandlerInterface extends EventEmitterInterface
{
    /**
     * @param WebSocketTransportInterface $user
     * @return mixed
     */
    public function addConnection(WebSocketTransportInterface $user);

    /**
     * @param WebSocketTransportInterface $user
     * @return mixed
     */
    public function removeConnection(WebSocketTransportInterface $user);

    /**
     * @return WebSocketTransportInterface[]
     */
    public function getConnections();
}
