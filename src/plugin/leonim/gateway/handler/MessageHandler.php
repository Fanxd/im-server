<?php
namespace plugin\leonim\gateway\handler;

use GatewayWorker\Lib\Gateway;
use plugin\leonim\app\model\User;
use plugin\leonim\app\service\MessageService;
use plugin\leonim\gateway\Response;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use plugin\leonim\app\service\ChatService;

class MessageHandler
{
    /**
     * 发送消息处理
     *
     * @param string $clientId 客户端连接ID
     * @param array $data 消息数据
     * @param mixed $requestId 请求ID，用于响应关联
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public static function send(string $clientId, array $data, mixed $requestId): void
    {
        $messageType = $data['messageType'] ?? 'text';
        $fromUid = Gateway::getUidByClientId($clientId);
        $toUid = $data['toUid'] ?? '';
        $groupId = $data['groupId'] ?? '';
        $messageContent = $data['messageContent'] ?? '';
        $chatId = $data['chatId'] ?? '';

        if (empty($fromUid)) {
            // 缺少发送者标识，回复错误信息
            Gateway::sendToCurrentClient(Response::make('message', $requestId, 1, '缺少发送者标识 `from`'));
            return;
        }

        // 校验 chatId 是否有效
        if (empty($chatId)) {
            // chatId 为空，返回错误响应
            Gateway::sendToCurrentClient(Response::make('message', $requestId, 400, '无效的会话ID'));
            return;
        }

        // 检查会话是否存在
        $chat = ChatService::getChatById($chatId);

        if ($chat->isEmpty()) {
            // 会话不存在，返回错误响应
            Gateway::sendToCurrentClient(Response::make('message', $requestId, 404, '会话不存在'));
            return;
        }

        // 如果是群聊，验证会话类型是否为群聊类型
        if (!empty($groupId)) {
            // 如果会话类型不是群聊，则返回错误
            if ($chat->type != 2) {
                Gateway::sendToCurrentClient(Response::make('message', $requestId, 400, '会话不是群聊'));
                return;
            }
        }

        $user = User::where('uuid', $fromUid)->find();

        // 保存消息到数据库，并获取数据库生成的消息ID
        $dbMsg = MessageService::saveMessage(
            $chatId,
            $user->id,
            self::convertType($messageType),
            $messageContent
        );

        // 构建消息数据结构
        $messageData = [
            'chatId' => $chatId,
            'groupId' => $groupId,
            // chatType: "group" 表示群聊，"private" 表示私聊
            'chatType' => !empty($groupId) ? 'group' : 'private',
            'sender' => [
                'uuid' => $fromUid,
                'name' => $user->nickname,
                'avatar' => $user->avatar,
            ],
            // 消息内容相关字段，统一放在 message 键下
            'message' => [
                'id' => $dbMsg->id,       // 唯一消息ID（数据库ID）
                'type' => $messageType,
                'content' => $messageContent,
                'createdAt' => time(),
            ],
        ];

        // 私聊消息逻辑
        if (!empty($toUid)) {
            echo "私聊 to: { $toUid } content: { $messageContent } \n";

            Gateway::sendToUid($toUid, Response::make('message', $requestId, 0, 'success', $messageData));
        }

        // 群发消息逻辑
        if (!empty($groupId)) {
            echo "群聊 to: { $groupId } content: { $messageContent } \n";

            Gateway::sendToGroup($groupId, Response::make('message', $requestId, 0, 'success', $messageData));
        }

        // 给发送者自己也发送一份消息，作为本地确认
        Gateway::sendToCurrentClient(Response::make('message', $requestId, 0, 'success', $messageData));
    }

    /**
     * 发送当前在线用户列表给指定客户端
     *
     * @param string $client_id 客户端连接ID
     * @param mixed $requestId 请求ID，用于响应关联
     */
    public static function sendOnlineUsers(string $client_id, mixed $requestId): void
    {
        $allUids = Gateway::getAllUidList();

        Gateway::sendToClient($client_id, Response::make('onlineUsers', $requestId, 0, 'success', [
            'users' => array_values($allUids),
        ]));
    }

    /**
     * 广播当前在线用户列表给所有客户端
     */
    public static function broadcastOnlineUsers(): void
    {
        $allUids = Gateway::getAllUidList();

        Gateway::sendToAll(Response::make('onlineUsers', null, 0, 'success', [
            'users' => array_values($allUids),
        ]));
    }

    /**
     * 消息类型转换，将字符串类型转换为数据库中对应的整数类型
     *
     * @param string $type 消息类型字符串
     * @return int 对应的整数类型
     */
    protected static function convertType(string $type): int
    {
        return match ($type) {
            'text' => 1,
            'image' => 2,
            'video' => 3,
            'voice' => 4,
            'call_voice' => 5,
            'call_video' => 6,
            default => 1,
        };
    }
}
