<?php

namespace plugin\leonim\app\controller;

use plugin\leonim\app\model\ConversationMembers;
use plugin\leonim\app\model\Conversations;
use plugin\leonim\app\model\Messages;
use plugin\leonim\app\model\MessageUserDeleted;
use plugin\leonim\app\model\User;
use plugin\leonim\app\validate\ConversationValidate;
use support\Request;
use support\Response;
use think\facade\Db;

class ConversationController extends Base
{
    public function create(Request $request): Response
    {
        $data = $request->post() + ['user_id' => $request->user['id']];
        $this->validate($data, ConversationValidate::class, 'create');

        $userId = $data['user_id'];
        $type = (int)($data['type'] ?? 1);
        $targetUuid = $data['target_id'] ?? null;

        // -------------------------
        // 单聊
        // -------------------------
        if ($type === 1 && $targetUuid) {
            $targetId = (int)($this->uuidToId($targetUuid) ?? 0);
            $conversation = Conversations::where('type', 1)
                ->whereIn('id', function ($q) use ($userId) {
                    $q->name((new ConversationMembers())->getTable())
                        ->field('conversation_id')
                        ->where('user_id', $userId);
                })
                ->whereIn('id', function ($q) use ($targetId) {
                    $q->name((new ConversationMembers())->getTable())
                        ->field('conversation_id')
                        ->where('user_id', $targetId);
                })
                ->find();

            if ($conversation) {
                return $this->success(['conversation_id' => $conversation->id], '会话已存在');
            }
        }

        // -------------------------
        // 群聊成员处理
        // -------------------------
        $memberIds = [$userId]; // 默认包含创建者

        if ($type !== 1 && $targetUuid) {
            // 拆分字符串成数组
            $uuidArr = explode(',', $targetUuid);
            $uuidArr = array_unique($uuidArr); // 去重
            foreach ($uuidArr as $uuid) {
                $id = $this->uuidToId($uuid);
                if ($id && !in_array($id, $memberIds)) {
                    $memberIds[] = $id;
                }
            }
        }

        // -------------------------
        // 群聊默认名称
        // -------------------------
        $name = $data['name'] ?? null;
        if (!$name && $type !== 1) {
            $names = User::whereIn('id', $memberIds)->limit(3)->column('nickname');
            $name = implode('、', $names) . '的群聊';
        }

        // -------------------------
        // 创建会话
        // -------------------------
        $conversation = Conversations::create([
            'type' => $type,
            'name' => $name,
            'avatar' => $data['avatar'] ?? '',
            'target_id' => null, // 群聊 target_id 可以为空
            'is_active' => 1
        ]);

        // -------------------------
        // 插入成员
        // -------------------------
        $membersData = [];
        foreach ($memberIds as $id) {
            $membersData[] = [
                'conversation_id' => $conversation->id,
                'user_id' => $id,
                'role' => $id == $userId ? 3 : 1
            ];
        }
        ConversationMembers::insertAll($membersData);

        return $this->success(['conversation_id' => $conversation->id], '会话创建成功');
    }

    /**
     * 清空会话消息（个人视图删除）
     * A 删除聊天记录，只对自己生效，其他成员仍可查看
     */
    public function clearMessages(Request $request): Response
    {
        $data = $request->post() + ['user_id' => $request->user['id']];

        $this->validate($data, ConversationValidate::class, 'clear');

        $userId = $data['user_id'];
        $conversationId = (int)$data['conversation_id'];

        // 获取会话中未被当前用户删除的消息
        $messages = Messages::where('conversation_id', $conversationId)
            ->select();

        if ($messages->isEmpty()) {
            return $this->success([], '会话消息已清空');
        }

        $now = date('Y-m-d H:i:s');
        $dataInsert = [];

        foreach ($messages as $msg) {
            // 判断当前用户是否已删除
            if ($msg->deletedUsers()->where('user_id', $userId)->count() === 0) {
                $dataInsert[] = [
                    'message_id' => $msg->id,
                    'user_id' => $userId,
                    'deleted_at' => $now
                ];
            }
        }

        if ($dataInsert) {
            // 使用关联模型批量保存
            (new MessageUserDeleted())->saveAll($dataInsert);
        }

        return $this->success([], '会话消息已清空');
    }

    /**
     * 删除会话或退出群
     */
    public function delete(Request $request): Response
    {
        $data = $request->post() + ['user_id' => $request->user['id']];

        $this->validate($data, ConversationValidate::class, 'delete');

        $userId = $data['user_id'];
        $conversationId = (int)$data['conversation_id'];

        $conversation = Conversations::find($conversationId);
        if (!$conversation) return $this->fail('会话不存在');

        $member = ConversationMembers::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->find();

        if ($conversation->type === 1) {
            // 单聊删除成员即可
            $member->delete();
        } else {
            // 群聊：群主解散，普通成员退出
            if ($member->role === 3) {
                $conversation->is_active = 0;
                $conversation->save();
            } else {
                $member->delete();
            }
        }

        return $this->success([], '会话已删除/退出');
    }

    /**
     * 设置会话已读
     */
    public function setRead(Request $request): Response
    {
        $data = $request->post() + ['user_id' => $request->user['id']];
        $this->validate($data, ConversationValidate::class, 'read');

        $userId = $data['user_id'];
        $conversationId = (int)$data['conversation_id'];

        $member = ConversationMembers::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->find();

        $latestMessage = Messages::where('conversation_id', $conversationId)
            ->order('id', 'desc')
            ->find();

        if ($latestMessage) {
            $member->last_read_message_id = $latestMessage->id;
            $member->save();
        }

        return $this->success([], '会话已标记为已读');
    }

    /**
     * 获取当前用户所有会话未读总数
     */
    public function unreadTotal(Request $request): Response
    {
        $data = $request->get() + ['user_id' => $request->user['id']];
        $this->validate($data, ConversationValidate::class, 'unread');

        $userId = $data['user_id'];

        $members = ConversationMembers::where('user_id', $userId)
            ->with(['conversation' => function ($q) {
                $q->where('is_active', 1);
            }])
            ->select();

        $totalUnread = 0;
        foreach ($members as $member) {
            $lastReadId = $member->last_read_message_id ?? 0;
            $unreadCount = Messages::where('conversation_id', $member->conversation_id)
                ->where('id', '>', $lastReadId)
                ->count();
            $totalUnread += $unreadCount;
        }

        return $this->success(['total_unread' => $totalUnread], '获取成功');
    }

    /**
     * 获取会话列表（分页 + 关联查询优化）
     */
    public function list(Request $request): Response
    {
        $data = $request->get() + ['user_id' => $request->user['id']];
        $this->validate($data, ConversationValidate::class, 'list');

        $userId = $data['user_id'];
        $page = (int)($data['page'] ?? 1);
        $limit = (int)($data['limit'] ?? 20);

        // 关联 conversation 和 conversation->members->user，避免 N+1
        $query = ConversationMembers::with([
            'conversation' => function ($q) {
                $q->where('is_active', 1)->with(['members.user']);
            }
        ])->where('user_id', $userId);

        $members = $query->page($page, $limit)->select();

        $list = [];

        foreach ($members as $member) {
            $conv = $member->conversation;
            if (!$conv) continue;

            // 获取最后一条消息
            $lastMessage = Messages::where('conversation_id', $conv->id)
                ->order('id', 'desc')
                ->find();

            // 安全判断：可能为空
            $client_id = $lastMessage->id ?? '';
            $lastContent = $lastMessage->content ?? '';
            $send_time = $lastMessage->created_at ?? '';
            $message_type = $lastMessage->type ?? 1;
            $status = $lastMessage->status ?? 1;
            $send_id = $lastMessage ? $this->idToUuid($lastMessage->from_user_id) : '';
            $send_name = $send_id ? User::where('id', $lastMessage->from_user_id)->value('nickname') : '';

            // 未读数统计
            $unreadCount = Messages::where('conversation_id', $conv->id)
                ->where('id', '>', $member->last_read_message_id ?? 0)
                ->count();

            $uuid = $conv->uuid;
            $name = $conv->name;
            $avatar = $conv->avatar;

            // 单聊显示对方昵称头像
            if ($conv->type === 1) {
                foreach ($conv->members as $m) {
                    if ($m->user_id != $userId) {
                        $uuid = $m->user->uuid ?? 'U';
                        $name = $m->user->nickname ?? '用户';
                        $avatar = $m->user->avatar ?? '';
                        break;
                    }
                }
            }

            $list[] = [
                'conversation_id' => $conv->id,
                'conversation_type' => $conv->type,
                'user' => [
                    'uuid' => $uuid,
                    'name' => $name,
                    'avatar' => $avatar,
                ],
                'message' => [
                    'client_id' => $client_id,
                    'send_id' => $send_id,
                    'send_name' => $send_name,
                    'content' => $lastContent,
                    'message_type' => $message_type,
                    'send_time' => $send_time,
                    'status' => $status,
                ],
                'unread_count' => $unreadCount,
            ];
        }

        return $this->success([
            'list' => $list,
            'page' => $page,
            'limit' => $limit,
            'total' => count($list),
        ]);
    }

    /**
     * 获取会话详情
     * 当前用户看不到自己已删除的消息
     */
    public function detail(Request $request): Response
    {
        $data = $request->get() + ['user_id' => $request->user['id']];
        $this->validate($data, ConversationValidate::class, 'detail');

        $userId = $data['user_id'];
        $conversationId = (int)$data['conversation_id'];
        $page = (int)($data['page'] ?? 1);
        $limit = (int)($data['limit'] ?? 20);

        // 检查用户是否属于该会话
        $member = ConversationMembers::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->find();

        if (!$member) {
            return $this->fail('无权限查看该会话');
        }

        // 获取会话消息，排除当前用户已删除的消息
        $messages = Messages::where('conversation_id', $conversationId)
            ->with('user')
            ->whereNotIn('id', function ($query) use ($userId) {
                $query->table((new MessageUserDeleted())->getTable())
                    ->where('user_id', $userId)
                    ->field('message_id');
            })
            ->order('id', 'asc')
            ->page($page, $limit)
            ->select();

        $formatMessages = [];
        foreach ($messages as $message) {
            $formatMessages[] = [
                'id' => $message->id,
                'send_id' => $message->user->uuid,
                'send_avatar' => $message->user->avatar,
                'send_nickname' => $message->user->nickname,
                'content_type' => $message->type,
                'content' => $message->content,
                'send_time' => $message->created_at,
            ];
        }

        $type = Conversations::where('id', $conversationId)->value('type');

        $members = $member; // 默认单聊返回当前用户所在记录
        if ($type == 2) {
            // 获取群聊成员 user_id 转 uuid，并用逗号拼接
            $members = ConversationMembers::where('conversation_id', $conversationId)
                ->column('user_id'); // 获取 user_id 数组
            $members = array_map(function ($id) {
                return User::where('id', $id)->value('uuid') ?? '';
            }, $members);
            $members = implode(',', $members); // 拼接成字符串
        }

        return $this->success([
            'conversation_id' => $conversationId,
            'conversation_type' => $type,
            'conversation_members' => $members,
            'messages' => $formatMessages
        ]);
    }
}
