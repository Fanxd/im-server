<?php
namespace LeonIm\ImServer\leonim\gateway;

use GatewayWorker\Lib\Gateway;
use Workerman\Worker;

class Events
{
    /**
     * Worker 启动事件
     * 在 BusinessWorker 启动时触发
     */
    public static function onWorkerStart(Worker $worker): void
    {
        echo "BusinessWorker {$worker->id} started\n";
    }

    /**
     * 新客户端连接事件
     * 每当有新的 client_id 连接上来时触发
     */
    public static function onConnect($client_id): void
    {
        // 发送欢迎消息给新连接的客户端
        self::send($client_id, [
            'type'      => 'connect',
            'client_id' => $client_id,
            'message'   => 'Connected successfully'
        ]);
    }

    /**
     * 客户端消息事件
     * 处理客户端发来的消息
     */
    public static function onMessage($client_id, $message): void
    {
        // 尝试解析 JSON 数据
        $data = json_decode($message, true);

        if (!$data) {
            self::send($client_id, [
                'type'    => 'error',
                'message' => 'Invalid JSON format'
            ]);
            return;
        }

        // 根据消息类型分发逻辑
        switch ($data['type'] ?? '') {
            case 'bind': // 用户绑定 UID
                self::handleBind($client_id, $data);
                break;

            case 'say': // 发送消息（私聊 / 群发）
                self::handleSay($client_id, $data);
                break;

            case 'get_online_users': // 请求在线用户列表
                self::sendOnlineUsers($client_id);
                break;

            default:
                self::send($client_id, [
                    'type'    => 'error',
                    'message' => 'Unknown message type'
                ]);
        }
    }

    /**
     * 客户端断开连接事件
     */
    public static function onClose($client_id): void
    {
        // 通知所有人有用户断开连接
        try {
            Gateway::sendToAll(json_encode([
                'type' => 'disconnect',
                'client_id' => $client_id
            ]));
        } catch (\Exception $e) {
        }

        // 广播最新的在线用户列表
        self::broadcastOnlineUsers();
    }

    /**
     * 处理绑定 UID 逻辑
     */
    protected static function handleBind($client_id, array $data): void
    {
        if (!empty($data['uid'])) {
            $uid = $data['uid'];

            // 将 client_id 与 uid 绑定
            Gateway::bindUid($client_id, $uid);

            // 通知该客户端绑定成功
            self::send($client_id, [
                'type'   => 'bind',
                'status' => 'success',
                'uid'    => $uid
            ]);

            // 广播在线用户列表
            self::broadcastOnlineUsers();
        } else {
            self::send($client_id, [
                'type'    => 'error',
                'message' => 'UID is required for bind'
            ]);
        }
    }

    /**
     * 处理消息发送逻辑
     */
    protected static function handleSay($client_id, array $data): void
    {
        $from_uid = $data['from'] ?? $client_id;
        $to_uid   = $data['to']   ?? null;
        $content  = $data['content'] ?? '';

        $msg = [
            'type'    => 'message',
            'from'    => $from_uid,
            'content' => $content
        ];

        if ($to_uid) {
            // 私聊：发给指定 UID
            Gateway::sendToUid($to_uid, json_encode($msg, JSON_UNESCAPED_UNICODE));
        } else {
            // 群发：发给所有人
            Gateway::sendToAll(json_encode($msg, JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * 发送当前在线用户列表给指定客户端
     */
    protected static function sendOnlineUsers($client_id): void
    {
        $onlineUids = Gateway::getAllUidList();
        self::send($client_id, [
            'type'  => 'online_users',
            'users' => array_values($onlineUids)
        ]);
    }

    /**
     * 广播所有在线用户列表
     */
    protected static function broadcastOnlineUsers(): void
    {
        $allUids = Gateway::getAllUidList();
        $users   = !empty($allUids) ? array_values($allUids) : [];

        Gateway::sendToAll(json_encode([
            'type'  => 'online_users',
            'users' => $users
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 统一封装的消息发送方法
     */
    protected static function send($client_id, array $data): void
    {
        Gateway::sendToClient($client_id, json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
