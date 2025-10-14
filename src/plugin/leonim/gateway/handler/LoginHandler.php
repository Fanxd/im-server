<?php
namespace plugin\leonim\gateway\handler;

use GatewayWorker\Lib\Gateway;
use plugin\leonim\app\model\User;
use plugin\leonim\gateway\Response;

class LoginHandler
{
    public static function handle(string $client_id, array $data, ?string $requestId = null): void
    {

        $user = User::where('uuid', $data['uid'])->findOrEmpty();

        if ($user->isEmpty()) {
            Gateway::sendToClient($client_id, Response::make('login', $requestId, 404, 'User not found'));
            return;
        }

        $userInfo = [
            'uuid' => $user->uuid,
            'username' => $user->username,
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
            'email' => $user->email,
            'created_at' => $user->created_at,
        ];

        // 发送登录成功响应
        Gateway::sendToClient($client_id, Response::make('login', $requestId, 200, 'Login success', $userInfo));

        // 广播当前在线用户列表
        MessageHandler::broadcastOnlineUsers();
    }
}
