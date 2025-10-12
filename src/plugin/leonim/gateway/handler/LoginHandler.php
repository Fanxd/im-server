<?php

namespace plugin\leonim\gateway\handler;

use GatewayWorker\Lib\Gateway;
use plugin\leonim\gateway\Response;
use plugin\leonim\gateway\Auth;
use plugin\leonim\gateway\handler\MessageHandler;

class LoginHandler
{
    public static function handle(string $client_id, array $data): void
    {
        $userID = $data['uid'] ?? '';
        $token = $data['token'] ?? '';

        if (!$userID || !Auth::verifyToken($token, $userID)) {
            Gateway::sendToClient($client_id, Response::fail('Invalid token', 401));
            Gateway::closeClient($client_id);
            return;
        }

        Gateway::bindUid($client_id, $userID);
        Auth::setAuth($client_id);

        Gateway::sendToClient($client_id, Response::ok('loginSuccess', [
            'userID' => $userID,
            'token' => $token
        ]));

        MessageHandler::broadcastOnlineUsers();
    }
}
