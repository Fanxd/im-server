<?php
namespace plugin\leonim\app\validate;

use plugin\leonim\app\model\User;
use plugin\leonim\app\model\Friends;
use Tinywan\Jwt\JwtToken;
use Tinywan\Validate\Validate;

class FriendValidate extends Validate
{
    // 验证规则
    protected array $rule = [
        'friend_id'  => 'require|integer|min:1|checkFriend',
        'request_id' => 'require|integer|min:1',
        'message'    => 'max:255',
    ];

    // 场景
    protected array $scene = [
        'add'    => ['friend_id', 'message'],
        'accept' => ['request_id'],
        'reject' => ['request_id'],
    ];

    // 验证提示信息（中文）
    protected array $message = [
        'friend_id.require'  => '好友ID不能为空',
        'friend_id.integer'  => '好友ID必须是整数',
        'friend_id.min'      => '好友ID必须大于0',
        'request_id.require' => '请求ID不能为空',
        'request_id.integer' => '请求ID必须是整数',
        'request_id.min'     => '请求ID必须大于0',
        'message.max'        => '留言不能超过255个字符',
    ];

    /**
     * 自定义验证规则：检查好友添加合法性
     */
    protected function checkFriend($value, $rule, $data)
    {
        $userId = JwtToken::getCurrentId(); // 当前登录用户ID

        if ($value == $userId) {
            return '不能添加自己为好友';
        }

        $friendUser = User::find($value);
        if (!$friendUser) {
            return '用户不存在';
        }

        // 是否已是好友
        if (Friends::where('user_id', $userId)->where('friend_id', $value)->exists()) {
            return '已经是好友';
        }

        return true; // 验证通过
    }
}
