<?php
namespace plugin\leonim\gateway;

use GatewayWorker\Lib\Gateway;
use Workerman\Worker;
use plugin\leonim\gateway\handler\{
    LoginHandler,
    MessageHandler,
    FriendHandler,
    HeartbeatHandler
};

class Events
{
    public static function onWorkerStart(Worker $worker): void
    {
        echo "✅ Worker 启动 #{$worker->id}\n";
    }

    public static function onConnect($client_id): void
    {
        echo "🆕 客户端连接: $client_id\n";
        Gateway::sendToClient($client_id, Response::ok('welcome', [
            'client_id' => $client_id,
            'hint'      => '请先登录'
        ]));
    }

    public static function onMessage($client_id, $message): void
    {
        $data = json_decode($message, true);
        if (!$data) {
            Gateway::sendToClient($client_id, Response::fail('Invalid JSON'));
            return;
        }

        $type = $data['type'] ?? '';

        // ✅ 登录逻辑独立
        if ($type === 'login') {
            LoginHandler::handle($client_id, $data);
            return;
        }

        // ✅ 校验是否已认证
        if (!Auth::isAuth($client_id)) {
            Gateway::sendToClient($client_id, Response::fail('Unauthorized', 401));
            return;
        }

        // ✅ 消息分发
        match ($type) {
            'heartbeat' => HeartbeatHandler::handle($client_id),
            'sendMessage' => MessageHandler::send($client_id, $data),
            'getUsersOnline' => MessageHandler::sendOnlineUsers($client_id),
            'friendRequest' => FriendHandler::request($client_id, $data),
            default => Gateway::sendToClient($client_id, Response::fail("Unknown type: {$type}", 404))
        };
    }

    public static function onClose($client_id): void
    {
        echo "❌ 客户端断开: $client_id\n";
        Auth::removeAuth($client_id);

        Gateway::sendToAll(Response::ok('disconnect', [
            'client_id' => $client_id
        ]));

        MessageHandler::broadcastOnlineUsers();
    }
}
