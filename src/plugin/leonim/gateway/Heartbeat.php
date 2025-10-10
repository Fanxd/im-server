<?php
namespace plugin\leonim\gateway;

use GatewayWorker\Lib\Gateway;

/**
 * Class Heartbeat
 * 处理 WebSocket 心跳请求
 */
class Heartbeat
{
    /**
     * 心跳响应
     *
     * @param string $client_id
     */
    public static function handle(string $client_id): void
    {
        Gateway::sendToClient(
            $client_id,
            Response::format('heartbeat', ['timestamp' => time()])
        );
    }
}
