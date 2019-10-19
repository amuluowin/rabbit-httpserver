<?php


namespace rabbit\httpserver\websocket;

use rabbit\App;

/**
 * Class CloseHandler
 * @package rabbit\httpserver\websocket
 */
class CloseHandler implements CloseHandlerInterface
{
    /**
     * @param \Swoole\WebSocket\Server $server
     * @param \Swoole\Websocket\Frame $frame
     * @throws \Exception
     */
    public function handle(\Swoole\Websocket\Frame $frame): void
    {
        App::warning(sprintf("The fd=%d is closed.code=%s reason=%s!", $frame->fd, $frame->code, $frame->reason));
    }
}
