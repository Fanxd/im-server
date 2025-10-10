<?php
namespace plugin\leonim\gateway;

use GatewayWorker\Lib\Gateway;
use Workerman\Worker;

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

        // ✅ 登录逻辑
        if ($type === 'login') {
            self::handleLogin($client_id, $data);
            return;
        }

        // ✅ 非登录必须验证
        if (!Auth::isAuth($client_id)) {
            Gateway::sendToClient($client_id, Response::fail('Unauthorized', 401));
            return;
        }

        switch ($type) {
            case 'heartbeat':
                Heartbeat::handle($client_id);
                break;

            case 'sendMessage':
                Message::sendMsg($client_id, $data);
                break;

            case 'getUsersOnline':
                Message::sendOnlineUsers($client_id);
                break;

            default:
                Gateway::sendToClient($client_id, Response::fail("Unknown type: {$type}", 404));
                break;
        }
    }

    public static function onClose($client_id): void
    {
        echo "❌ 客户端断开: $client_id\n";
        Auth::removeAuth($client_id);

        Gateway::sendToAll(Response::ok('disconnect', [
            'client_id' => $client_id
        ]));

        Message::broadcastOnlineUsers();
    }

    protected static function handleLogin($client_id, array $data): void
    {
        $userID = $data['uid'] ?? '';
        $token  = $data['token'] ?? '';

        if (!$userID || !Auth::verifyToken($token, $userID)) {
            Gateway::sendToClient($client_id, Response::fail('Invalid token', 401));
            Gateway::closeClient($client_id);
            return;
        }

        Gateway::bindUid($client_id, $userID);
        Auth::setAuth($client_id);

        Gateway::sendToClient($client_id, Response::ok('loginSuccess', [
            'userID' => $userID,
            'token'  => $token
        ]));

        Message::broadcastOnlineUsers();
    }
}
