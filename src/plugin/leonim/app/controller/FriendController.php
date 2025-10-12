<?php
namespace plugin\leonim\app\controller;

use plugin\leonim\app\model\FriendRequests;
use plugin\leonim\app\model\Friends;
use plugin\leonim\app\model\User;
use plugin\leonim\app\model\UserBlacklist;
use plugin\leonim\app\validate\FriendValidate;
use support\Request;
use support\Response;

/**
 * 好友与黑名单管理控制器
 */
class FriendController extends Base
{
    /**
     * 添加好友
     */
    public function add(Request $request): Response
    {
        $data = [
            'friend_id' => $request->post('friend_id'),
            'message'   => $request->post('message', ''),
            'user_id'   => $request->user['id'],
            'uuid'      => $request->user['uuid'],
        ];

        // 验证添加好友逻辑，包括黑名单约束
        $this->validate($data, FriendValidate::class, 'add');

        FriendRequests::create([
            'from_user_id' => $data['uuid'],
            'to_user_id'   => $data['friend_id'],
            'message'      => $data['message'],
            'status'       => 0,
            'is_read'      => 0,
        ]);

        return $this->success([], '好友请求已发送');
    }

    /**
     * 删除好友
     */
    public function delete(Request $request): Response
    {
        $data = [
            'friend_uuid' => $request->post('friend_uuid'),
            'user_uuid'   => $request->user['uuid'],
        ];

        $this->validate($data, FriendValidate::class, 'delete');

        $userId   = User::uuidToId($data['user_uuid']);
        $friendId = User::uuidToId($data['friend_uuid']);

        Friends::where('user_id', $userId)->where('friend_id', $friendId)->delete();
        Friends::where('user_id', $friendId)->where('friend_id', $userId)->delete();

        return $this->success([], '删除好友成功');
    }

    /**
     * 同意好友申请
     */
    public function accept(Request $request): Response
    {
        $data = [
            'request_id' => $request->post('request_id', 0),
            'user_uuid'  => $request->user['uuid'],
        ];

        // 验证请求是否合法 & 是否在黑名单中
        $this->validate($data, FriendValidate::class, 'accept');

        $friendRequest = FriendRequests::find($data['request_id']);

        // 更新申请状态
        $friendRequest->status = 1;
        $friendRequest->is_read = 1;
        $friendRequest->updated_at = date('Y-m-d H:i:s');
        $friendRequest->save();

        $fromUuid = User::uuidToId($friendRequest->from_user_id);
        $toUuid   = User::uuidToId($friendRequest->to_user_id);

        // 写入双方好友关系（双向）
        if (!Friends::where('user_id', $fromUuid)->where('friend_id', $toUuid)->find()) {
            Friends::create([
                'user_id'   => $fromUuid,
                'friend_id' => $toUuid,
                'remark'    => $friendRequest->remark ?? '',
                'group_name'=> $friendRequest->group_name ?? '',
                'tags'      => $friendRequest->tags ?? '',
                'created_at'=> date('Y-m-d H:i:s'),
                'updated_at'=> date('Y-m-d H:i:s'),
            ]);
        }

        if (!Friends::where('user_id', $toUuid)->where('friend_id', $fromUuid)->find()) {
            Friends::create([
                'user_id'   => $toUuid,
                'friend_id' => $fromUuid,
                'remark'    => '',
                'group_name'=> '',
                'tags'      => '',
                'created_at'=> date('Y-m-d H:i:s'),
                'updated_at'=> date('Y-m-d H:i:s'),
            ]);
        }

        return $this->success([], '已同意好友申请');
    }

    /**
     * 拒绝好友申请
     */
    public function reject(Request $request): Response
    {
        $requestId = $request->post('request_id', 0);
        $userUuid  = $request->user['uuid'];

        $updated = FriendRequests::where('id', $requestId)
            ->where('to_user_id', $userUuid)
            ->where('status', 0)
            ->update([
                'status' => 2,
                'is_read' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if (!$updated) {
            return $this->fail('好友申请已处理或不存在');
        }

        return $this->success([], '已拒绝好友申请');
    }

    /**
     * 获取好友申请列表
     */
    public function requests(Request $request): Response
    {
        $userId = $request->user['uuid'];
        $page   = (int)$request->get('page', 1);
        $limit  = (int)$request->get('limit', 20);

        $query = FriendRequests::where('to_user_id', $userId)
            ->with(['fromUser'])
            ->order('created_at', 'desc');

        $list = $query->page($page, $limit)->select();

        $data = [];
        foreach ($list as $item) {
            $data[] = [
                'request_id'   => $item->id,
                'from_user_id' => $item->from_user_id,
                'nickname'     => $item->fromUser->nickname ?? '',
                'avatar'       => $item->fromUser->avatar ?? '',
                'message'      => $item->message,
                'status'       => $item->status,
                'is_read'      => $item->is_read,
                'created_at'   => $item->created_at,
                'updated_at'   => $item->updated_at,
            ];
        }

        $unreadCount = FriendRequests::where('to_user_id', $userId)
            ->where('is_read', 0)
            ->count();

        return $this->success([
            'list'        => $data,
            'unreadCount' => $unreadCount,
            'page'        => $page,
            'limit'       => $limit,
        ]);
    }

    /**
     * 获取未读好友申请数量
     */
    public function unreadCount(Request $request): Response
    {
        $userUuid = $request->user['uuid'];
        $count = FriendRequests::where('to_user_id', $userUuid)
            ->where('status', 0)
            ->where('is_read', 0)
            ->count();

        return $this->success(['unread_count' => $count], '获取成功');
    }

    /**
     * 查询好友列表
     */
    public function list(Request $request): Response
    {
        $userId = $request->user['id'];
        $keyword = $request->get('keyword', '');
        $page = (int)$request->get('page', 1);
        $limit = (int)$request->get('limit', 10);
        $enableGroup = (int)$request->get('group', 0);

        // 基础查询
        $query = Friends::with(['user' => function ($q) use ($keyword) {
            if ($keyword !== '') {
                $q->whereLike('nickname', "%{$keyword}%");
            }
            $q->field('id, uuid, nickname, avatar'); // 只取必要字段
        }])->where('user_id', $userId);

        // 备注 / 分组 / 标签 搜索
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('remark', "%{$keyword}%")
                    ->whereOrLike('group_name', "%{$keyword}%")
                    ->whereOrLike('tags', "%{$keyword}%");
            });
        }

        // 总数
        $total = $query->count();

        // 分页
        $list = $query->order('id', 'desc')
            ->page($page, $limit)
            ->select();

        // 不分组直接返回
        if ($enableGroup !== 1) {
            $result = $list->map(function ($item) {
                return [
                    'friend_uuid' => $item->user->uuid,
                    'uuid' => $item->user->uuid,
                    'nickname' => $item->user->nickname,
                    'avatar' => $item->user->avatar,
                    'remark' => $item->remark,
                    'tags' => $item->tags,
                    'group_name' => $item->group_name,
                ];
            });

            return $this->success([
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'list' => $result,
            ]);
        }

        // 按分组返回
        $grouped = [];
        foreach ($list as $item) {
            $group = $item->group_name ?: '默认分组';
            $grouped[$group][] = [
                'friend_uuid' => $item->user->uuid,
                'nickname' => $item->user->nickname,
                'avatar' => $item->user->avatar,
                'remark' => $item->remark,
                'tags' => $item->tags,
            ];
        }

        return $this->success([
            'total'  => $total,
            'page'   => $page,
            'limit'  => $limit,
            'groups' => $grouped,
        ]);
    }

    /**
     * 查询黑名单列表
     */
    public function blacklist(Request $request): Response
    {
        $userId  = $request->user['id'];
        $page    = (int)$request->get('page', 1);
        $limit   = (int)$request->get('limit', 20);
        $keyword = $request->get('keyword', '');

        $query = UserBlacklist::where('user_id', $userId)
            ->with(['blockedUser' => function ($q) use ($keyword) {
                if ($keyword) {
                    $q->where('username', 'like', "%{$keyword}%")
                        ->whereOr('nickname', 'like', "%{$keyword}%");
                }
            }])
            ->order('created_at', 'desc');

        $list = $query->page($page, $limit)->select();
        $filteredList = $list->filter(fn($item) => $item->blockedUser !== null);

        $total = UserBlacklist::where('user_id', $userId)
            ->Haswhere('blockedUser', function ($q) use ($keyword) {
                if ($keyword) {
                    $q->where('username', 'like', "%{$keyword}%")
                        ->whereOr('nickname', 'like', "%{$keyword}%");
                }
            })->count();

        $data = $filteredList->map(fn($item) => [
            'id'         => $item->id,
            'uuid'       => $item->blockedUser->uuid,
            'username'   => $item->blockedUser->username,
            'nickname'   => $item->blockedUser->nickname,
            'avatar'     => $item->blockedUser->avatar,
            'created_at' => $item->created_at,
        ]);

        return $this->success([
            'total' => $total,
            'list'  => $data,
        ]);
    }

    /**
     * 加入黑名单
     */
    public function addToBlacklist(Request $request): Response
    {
        $currentUserId = $request->user['id'];
        $blockedUuid   = $request->post('blocked_uuid');

        $this->validate([
            'blocked_uuid' => $blockedUuid,
            'user_id'      => $currentUserId,
        ], FriendValidate::class, 'black_add');

        $blockedUser = User::where('uuid', $blockedUuid)->find();

        // 删除好友关系
        Friends::where(function ($q) use ($currentUserId, $blockedUser) {
            $q->where('user_id', $currentUserId)->where('friend_id', $blockedUser->id);
        })->delete();

        Friends::where(function ($q) use ($currentUserId, $blockedUser) {
            $q->where('user_id', $blockedUser->id)->where('friend_id', $currentUserId);
        })->delete();

        UserBlacklist::create([
            'user_id'         => $currentUserId,
            'blocked_user_id' => $blockedUser->id,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        return $this->success([], '已加入黑名单');
    }

    /**
     * 从黑名单移除
     */
    public function removeFromBlacklist(Request $request): Response
    {
        $data = [
            'blocked_uuid' => $request->post('blocked_uuid'),
            'user_id'      => $request->user['id'],
        ];

        $this->validate($data, FriendValidate::class, 'black_remove');

        $blockedUser = User::where('uuid', $data['blocked_uuid'])->find();

        $record = UserBlacklist::where('user_id', $data['user_id'])
            ->where('blocked_user_id', $blockedUser->id)
            ->find();

        if ($record) $record->delete();

        return $this->success([], '已将用户移出黑名单');
    }
}
