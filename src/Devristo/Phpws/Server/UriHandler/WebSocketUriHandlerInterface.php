<?php
/**
 * Created by JetBrains PhpStorm.
 * User: chris
 * Date: 10/6/13
 * Time: 5:46 PM
 * To change this template use File | Settings | File Templates.
 */
namespace Devristo\Phpws\Server\UriHandler;

use Devristo\Phpws\Protocol\WebSocketConnectionInterface;
use Evenement\EventEmitterInterface;

interface WebSocketUriHandlerInterface extends EventEmitterInterface
{

    public function addConnection(WebSocketConnectionInterface $user);

    public function removeConnection(WebSocketConnectionInterface $user);

    /**
     * @return WebSocketConnectionInterface[]
     */
    public function getConnections();
}