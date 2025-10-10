<?php
namespace plugin\leonim\app\service;

use plugin\leonim\app\model\Messages;
use plugin\leonim\app\model\WaMessage;

class MessageService
{
    /**
     * 保存一条聊天记录
     * @param int|string $conversationId
     * @param int|string $fromUserId
     * @param int $type 1=text,2=image...
     * @param string $content
     * @param int $status
     * @return Messages
     */
    public static function saveMessage(int|string $conversationId, int|string $fromUserId, int $type, string $content, int $status = 1)
    {
        $message = new Messages();
        $message->conversation_id = $conversationId;
        $message->from_user_id = $fromUserId;
        $message->type = $type;
        $message->content = $content;
        $message->status = $status;
        $message->save();

        return $message;
    }
}
