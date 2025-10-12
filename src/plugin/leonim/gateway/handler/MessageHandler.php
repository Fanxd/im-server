<?php
namespace plugin\leonim\gateway\handler;

use GatewayWorker\Lib\Gateway;
use plugin\leonim\app\model\User;
use plugin\leonim\app\service\MessageService;
use plugin\leonim\gateway\Response;

class MessageHandler
{
    public static function send(string $client_id, array $data): void
    {
        $msgType = $data['msgType'] ?? 'text';
        $from = Gateway::getUidByClientId($client_id);
        $to = $data['to'] ?? '';
        $groupID = $data['groupID'] ?? '';
        $content = $data['content'] ?? '';
        $chatId = $data['chatId'] ?? '';

        if (empty($from)) {
            // 向当前客户端连接发送消息
            Gateway::sendToCurrentClient(Response::format('Missing sender `from`'));
            return;
        }
        $user = User::where('uuid', $from)->find();
        $msgData = [
            'msgID' => 'msg_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)), // 消息ID
            'chatId'    => $chatId,  // 会话ID
            'type'    => $msgType,  // 消息类型
            'content' => $content,  // 消息内容
            'created'    => time(), // 消息创建时间
            'user'    => [
                'uuid' => $from,
                'sender_name' => $user->nickname,
                'sender_avatar' => $user->avatar
            ], // 消息发送者信息
            'groupID' => $groupID
        ];
        // 写入数据库
        MessageService::saveMessage(
            $chatId,
            $user->id,
            self::convertType($msgType), // 转换为数据库 TINYINT
            $content,
        );

        // ✅ 群发
        if (!empty($groupID)) {
            echo "群聊 to: { $groupID } content: { $content } \n";

            Gateway::sendToGroup($groupID, Response::ok('message', $msgData));
            return;
        }

        // ✅ 单发
        if (!empty($to)) {
            echo "单发 to: { $to } content: { $content } \n";

            Gateway::sendToUid($to, Response::ok('message', $msgData));
        }

        // ✅ 给自己也发一份（本地确认）
        Gateway::sendToCurrentClient(Response::ok('message', $msgData));
    }

    public static function sendOnlineUsers(string $client_id): void
    {
        $allUids = Gateway::getAllUidList();
        Gateway::sendToClient($client_id, Response::ok('onlineUsers', [
            'users' => array_values($allUids)
        ]));
    }

    public static function broadcastOnlineUsers(): void
    {
        $allUids = Gateway::getAllUidList();
        Gateway::sendToAll(Response::ok('onlineUsers', [
            'users' => array_values($allUids)
        ]));
    }

    /**
     * 消息类型转换 text => 1, image => 2...
     */
    protected static function convertType($type)
    {
        return match ($type) {
            'text'  => 1,
            'image' => 2,
            'video' => 3,
            'voice' => 4,
            'call_voice' => 5,
            'call_video' => 6,
            default => 1
        };
    }
}
