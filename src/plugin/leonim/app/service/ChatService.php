<?php

namespace plugin\leonim\app\service;

use plugin\leonim\app\model\Conversations;
use plugin\leonim\app\model\User;
use think\db\Query;
use think\model\contract\Modelable;

/**
 * 会话
 */
class ChatService
{

    /**
     * 根据聊天ID获取聊天对象
     * @param string $chatId
     * @return array|mixed|Query|Modelable
     */
    static public function getChatById(string $chatId): mixed
    {
        return Conversations::where('id', $chatId)->findOrEmpty();
    }

    /**
     * 返回用户所在的所有有效群聊ID
     * @param int|string $uid 用户ID
     * @return array 群聊ID数组
     */
    static public function getUserGroupIds(int|string $uid): array
    {
        // 将UUID转换为用户ID
        $uid = User::uuidToId($uid);

        // 查询用户所在的有效群聊（type=2为群聊，is_active=1为有效）
        $groupIds = Conversations::where('type', 2)
            ->where('is_active', 1)
            // 使用hasWhere关联查询群聊成员表，筛选该用户所在的群聊
            ->hasWhere('members', function ($query) use ($uid) {
                $query->where('user_id', $uid);
            })
            // 使用表名指定字段，避免SQL字段冲突
            ->column((new Conversations)->getTable() . '.id');

        // 返回群聊ID数组，若无则返回空数组
        return $groupIds ?: [];
    }
}
