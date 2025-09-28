<?php
namespace plugin\leonim\app\validate;

use plugin\leonim\app\model\User;
use plugin\leonim\app\model\ConversationMembers;
use Tinywan\Validate\Validate;

class ConversationValidate extends Validate
{
    /**
     * 验证规则
     */
    protected array $rule = [
        'type' => 'require|in:1,2',                 // 会话类型：1=单聊,2=群聊
        'target_id' => 'require|checkTargetId',            // 单聊必须指定目标用户
        'name' => 'max:255',                        // 群聊名称最大长度
        'conversation_id' => 'require|checkMember',        // 检查当前用户是否属于会话
        'user_id' => 'require'                      // 当前用户ID必须传
    ];

    /**
     * 场景定义
     */
    protected array $scene = [
        'create' => ['type', 'target_id', 'name', 'user_id'],
        'clear'  => ['conversation_id', 'user_id'],
        'delete' => ['conversation_id', 'user_id'],
        'read'   => ['conversation_id', 'user_id'],
        'detail' => ['conversation_id', 'user_id'],
        'list'   => ['user_id'],
        'unread' => ['user_id']
    ];

    /**
     * 提示信息
     */
    protected array $message = [
        'type.require' => '会话类型不能为空',
        'type.in' => '会话类型不合法',
        'target_id.require' => '必须指定目标用户ID',
        'name.max' => '会话名称不能超过255个字符',
        'conversation_id.require' => '会话ID不能为空',
        'user_id.require' => '用户ID不能为空',
    ];

    /**
     * 自定义验证规则：单聊必须指定目标用户ID
     */
    protected function checkTargetId($value, $rule, $data): bool|string
    {
        // 仅单聊需要检查
        if (($data['type'] ?? 1) == 1) {
            if (!$value || $value <= 0) {
                return '单聊必须指定目标用户ID';
            }

            // 检查目标用户是否存在
            $targetUser = User::where('uuid', $value)->find();
            if (!$targetUser) {
                return '目标用户不存在';
            }
        }
        return true;
    }

    /**
     * 自定义验证规则：检查用户是否属于会话
     */
    protected function checkMember($value, $rule, $data): bool|string
    {
        $userId = $data['user_id'] ?? null;
        if (!$userId) return '无法获取当前用户信息';

        $member = ConversationMembers::where('conversation_id', $value)
            ->where('user_id', $userId)
            ->find();

        if (!$member) return '无权限操作该会话';

        return true;
    }
}
