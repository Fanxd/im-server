<?php

namespace plugin\leonim\gateway;

use Exception;
use GatewayWorker\Lib\Gateway;
use plugin\leonim\app\service\ChatService;
use plugin\leonim\gateway\handler\{FriendHandler, HeartbeatHandler, LoginHandler, MessageHandler};
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use Workerman\Worker;

class Events
{
    /**
     * Worker 启动回调
     * @param Worker $worker
     */
    public static function onWorkerStart(Worker $worker): void
    {
        echo "✅ Worker 启动 #{$worker->id}\n";
    }

    /**
     * WebSocket 握手时的连接认证回调
     * @param string $client_id
     * @param array $data
     */
    public static function onWebSocketConnect(string $client_id, array $data): void
    {
        // JWT 鉴权逻辑：
        // 注意：onWebSocketConnect 阶段不能发送数据，避免握手失败

        // 1. 优先从 query string 获取 token
        $token = $data['get']['token'] ?? '';

        // 2. 若 query string 无 token，尝试从 HTTP_AUTHORIZATION 头获取
        if (!$token && isset($data['server']['HTTP_AUTHORIZATION'])) {
            $authHeader = $data['server']['HTTP_AUTHORIZATION'];
            if (stripos($authHeader, 'Bearer ') === 0) {
                $token = substr($authHeader, 7);
            }
        }

        // 3. 若无 token，直接关闭连接
        if (!$token) {
            Gateway::closeClient($client_id);
            return;
        }

        // 4. 验证 token 有效性
        $user = Auth::verifyToken($token);
        if (!$user) {
            Gateway::closeClient($client_id);
            return;
        }

        // 5. 绑定 client_id 与用户信息（不发送数据）
        Gateway::bindUid($client_id, $user);

        // 6. 标记客户端已通过鉴权
        Auth::setAuth($client_id);

        // 用户连接后加入所属群组，保证群发消息能收到
        $groupIds = ChatService::getUserGroupIds($user);
        foreach ($groupIds as $groupId) {
            Gateway::joinGroup($client_id, $groupId);
        }
    }

    /**
     * 客户端连接回调
     * @param string $client_id
     */
    public static function onConnect(string $client_id): void
    {
        // 客户端连接事件，token 鉴权已在 onWebSocketConnect 完成
        echo "🆕 客户端连接: $client_id\n";
    }

    /**
     * 收到客户端消息回调
     * @param string $client_id
     * @param string $message
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function onMessage(string $client_id, string $message): void
    {
        // 解析前端传来的 JSON 数据
        $data = json_decode($message, true);

        // JSON 解析失败，返回错误信息
        if (!is_array($data)) {
            Gateway::sendToClient($client_id, Response::make('error', null, 400, '无效的 JSON 格式'));
            return;
        }

        // 获取请求 ID，若未提供则生成唯一 ID
        $requestId = $data['requestId'] ?? uniqid('req_');

        // 获取动作类型
        $action = $data['action'] ?? '';

        // 登录请求单独处理
        if ($action === 'login') {
            LoginHandler::handle($client_id, $data['data'], $requestId);
            return;
        }

        // 根据动作类型分发请求处理
        match ($action) {
            'heartbeat' => HeartbeatHandler::handleHeartbeat($client_id, $requestId),
            'sendMessage' => MessageHandler::send($client_id, $data['data'], $requestId),
            'getUsersOnline' => MessageHandler::sendOnlineUsers($client_id, $requestId),
            'sendFriendRequest' => FriendHandler::handleFriendRequest($client_id, $data['data'], $requestId),
            default => Gateway::sendToClient($client_id, Response::make($action, $requestId, 404, "未知动作: {$action}"))
        };
    }

    /**
     * 客户端断开连接回调
     * @param string $client_id
     * @throws Exception
     */
    public static function onClose(string $client_id): void
    {
        // 客户端断开连接事件
        echo "❌ 客户端断开: $client_id\n";

        // 移除客户端鉴权标记
        Auth::removeAuth($client_id);

        // 通知所有客户端有用户断开连接
        Gateway::sendToAll(Response::make('disconnect', null, 200, '客户端断开连接', [
            'client_id' => $client_id
        ]));

        // 广播当前在线用户列表
        MessageHandler::broadcastOnlineUsers();
    }
}
