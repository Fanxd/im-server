<?php
namespace plugin\leonim\app\validate;

use plugin\leonim\app\model\FriendRequests;
use plugin\leonim\app\model\Friends;
use plugin\leonim\app\model\User;
use plugin\leonim\app\model\UserBlacklist;
use Tinywan\Validate\Validate;

/**
 * 好友与黑名单验证器
 */
class FriendValidate extends Validate
{
    /**
     * 验证规则
     */
    protected array $rule = [
        'friend_id'    => 'require|min:1|checkFriend|checkRepeatApply', // 添加好友
        'request_id'   => 'require|min:1|checkRequestAccept|checkAcceptBlacklist', // 同意好友请求
        'message'      => 'max:255', // 好友请求留言
        'friend_uuid'  => 'require|checkDeleteFriend', // 删除好友
        'blocked_uuid' => 'require|min:1|checkCanBlacklist', // 加入黑名单
    ];

    /**
     * 场景定义
     */
    protected array $scene = [
        'add'           => ['friend_id', 'message'], // 添加好友
        'accept'        => ['request_id'],           // 同意好友申请
        'reject'        => ['request_id'],           // 拒绝好友申请
        'delete'        => ['friend_uuid'],          // 删除好友
        'black_add'     => ['blocked_uuid'],         // 加入黑名单
        'black_remove'  => ['blocked_uuid' => 'require|min:1|checkCanRemoveBlacklist'], // 移除黑名单
    ];

    /**
     * 验证提示信息（中文）
     */
    protected array $message = [
        'friend_id.require'             => '好友ID不能为空',
        'friend_id.integer'             => '好友ID必须是整数',
        'friend_id.min'                 => '好友ID必须大于0',
        'request_id.require'            => '请求ID不能为空',
        'request_id.integer'            => '请求ID必须是整数',
        'request_id.min'                => '请求ID必须大于0',
        'request_id.checkRequestAccept'=> '好友申请无效',
        'request_id.checkAcceptBlacklist'=> '无法同意黑名单用户的好友请求，请先移出黑名单',
        'message.max'                   => '留言不能超过255个字符',
        'friend_uuid.require'           => '好友UUID不能为空',
        'friend_uuid.checkDeleteFriend' => '无法删除该好友',
        'blocked_uuid.require'          => '用户UUID不能为空',
        'blocked_uuid.checkCanBlacklist'=> '该用户无法加入黑名单',
        'blocked_uuid.checkCanRemoveBlacklist'=> '该用户不在黑名单中',
    ];

    /**
     * 自定义验证：添加好友合法性
     */
    protected function checkFriend($value, $rule, $data): bool|string
    {
        $userId = $data['user_id'] ?? null; // 当前登录用户ID
        if (!$userId) return '无法获取当前用户信息';

        $user = User::find($userId);

        // 不能添加自己
        if ($value == $user->uuid) return '不能添加自己为好友';

        // 用户必须存在
        $friendUser = User::where('uuid', $value)->find();
        if (!$friendUser) return '用户不存在';

        // 检查好友关系
        if (Friends::where('user_id', $userId)->where('friend_id', User::uuidToId($value))->count()) {
            return '已经是好友';
        }

        // 检查是否在黑名单
        if (UserBlacklist::where('user_id', $userId)
            ->where('blocked_user_id', User::uuidToId($value))
            ->count()) {
            return '该用户在您的黑名单中，无法发送好友请求';
        }

        return true;
    }

    /**
     * 自定义验证：是否已经发送过好友请求
     */
    protected function checkRepeatApply($value, $rule, $data = []): bool|string
    {
        $uuid = $data['uuid'] ?? 0;

        $exists = FriendRequests::where('from_user_id', $uuid)
            ->where('to_user_id', $value)
            ->where('status', 0)
            ->count();

        return $exists > 0 ? '好友请求已发送，请等待对方处理' : true;
    }

    /**
     * 自定义验证：好友请求是否可接受
     */
    protected function checkRequestAccept($value, $rule, $data, $field, $title)
    {
        $userUuid = $data['user_uuid'] ?? null;
        if (!$userUuid) return '无法获取用户信息';

        $friendRequest = FriendRequests::where('id', $value)
            ->where('to_user_id', $userUuid)
            ->find();

        if (!$friendRequest) return '好友申请不存在';
        if ($friendRequest->status != 0) return '该申请已处理';

        return true;
    }

    /**
     * 自定义验证：同意好友请求前，检查请求方是否在黑名单
     */
    protected function checkAcceptBlacklist($value, $rule, $data): bool|string
    {
        $currentUserUuid = $data['user_uuid'] ?? null;
        if (!$currentUserUuid) return '无法获取当前用户信息';

        $friendRequest = FriendRequests::find($value);
        if (!$friendRequest) return '好友申请不存在';

        $currentUserId = User::uuidToId($currentUserUuid);
        $fromUserId    = User::uuidToId($friendRequest->from_user_id);

        // 请求方是否在当前用户黑名单中
        if (UserBlacklist::where('user_id', $currentUserId)
            ->where('blocked_user_id', $fromUserId)
            ->count()) {
            return '无法同意黑名单用户的好友请求，请先移出黑名单';
        }

        return true;
    }

    /**
     * 自定义验证：删除好友前校验
     */
    protected function checkDeleteFriend($value, $rule, $data = []): bool|string
    {
        $userUuid = $data['user_uuid'] ?? null;
        if (!$userUuid) return '无法获取当前用户信息';

        $friendUser = User::where('uuid', $value)->find();
        if (!$friendUser) return '要删除的用户不存在';

        $userId   = User::uuidToId($userUuid);
        $friendId = User::uuidToId($value);

        if (!$userId || !$friendId) return '用户信息错误';

        $exists = Friends::where('user_id', $userId)
            ->where('friend_id', $friendId)
            ->count();

        if ($exists <= 0) return '对方不是你的好友';

        return true;
    }

    /**
     * 自定义验证：检查是否可加入黑名单
     */
    protected function checkCanBlacklist($value, $rule, $data): bool|string
    {
        $userId = $data['user_id'] ?? null;
        if (!$userId) return '无法获取当前用户信息';

        $blockedUser = User::where('uuid', $value)->find();
        if (!$blockedUser) return '用户不存在';

        if ($blockedUser->id == $userId) return '不能拉黑自己';

        if (UserBlacklist::where('user_id', $userId)
            ->where('blocked_user_id', $blockedUser->id)
            ->count()) {
            return '该用户已在黑名单中';
        }

        return true;
    }

    /**
     * 自定义验证：检查是否可从黑名单移除
     */
    protected function checkCanRemoveBlacklist($value, $rule, $data): bool|string
    {
        $userId = $data['user_id'] ?? null;
        if (!$userId) return '无法获取当前用户信息';

        $blockedUser = User::where('uuid', $value)->find();
        if (!$blockedUser) return '用户不存在';

        $exists = UserBlacklist::where('user_id', $userId)
            ->where('blocked_user_id', $blockedUser->id)
            ->count();

        if ($exists <= 0) return '该用户不在黑名单中';

        return true;
    }
}
