<?php
namespace plugin\leonim\gateway\handler;

use GatewayWorker\Lib\Gateway;
use plugin\leonim\gateway\Response;

class HeartbeatHandler
{
    /**
     * 统一的心跳响应结构，包含字段：action, requestId, code, message, data
     *
     * @param string $client_id 客户端ID
     * @param mixed $requestId 请求ID
     */
    public static function handleHeartbeat(string $client_id, mixed $requestId): void
    {
        // 发送统一的 WebSocket 响应格式
        Gateway::sendToClient($client_id, Response::make(
            'heartbeat', // action
            $requestId,  // requestId
            200,         // code
            'pong',      // message
            [
                'server_time' => date('Y-m-d H:i:s')
            ]
        ));
    }
}
