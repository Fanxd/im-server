<?php

namespace plugin\leonim\gateway\handler;

use GatewayWorker\Lib\Gateway;
use plugin\leonim\gateway\Response;

class HeartbeatHandler
{
    public static function handle(string $client_id): void
    {
        Gateway::sendToClient($client_id, Response::ok('heartbeat', [
            'server_time' => date('Y-m-d H:i:s')
        ]));
    }
}
