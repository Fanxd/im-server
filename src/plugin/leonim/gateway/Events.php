<?php

namespace LeonIm\ImServer\plugin\leonim\gateway;

use GatewayWorker\Lib\Gateway;

class Events
{
    public static function onWorkerStart($worker): void
    {

    }

    public static function onConnect($client_id): void
    {

    }

    public static function onWebSocketConnect($client_id, $data): void
    {

    }

    public static function onMessage($client_id, $message): void
    {
        Gateway::sendToClient($client_id, "receive message $message");
    }

    public static function onClose($client_id): void
    {

    }

}
