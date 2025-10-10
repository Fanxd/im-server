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

class ConversationController extends Base
{
    /**
     * 创建会话
     */
    public function create(Request $request): Response
    {
        $data = $request->post() + ['user_id' => $request->user['id']];
        $this->validate($data, ConversationValidate::class, 'create');

        $userId = $data['user_id'];
        $type = (int)$data['type'];
        $targetId = (int)($this->uuidToId($data['target_id']) ?? 0);

        // 单聊：检查是否已有会话
        if ($type === 1) {
            $conversation = Conversations::where('type', 1)
                ->hasWhere('members', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->hasWhere('members', function ($q) use ($targetId) {
                    $q->where('user_id', $targetId);
                })
                ->find();
            if ($conversation) {
                return $this->success(['conversation_id' => $conversation->id], '会话已存在');
            }
        }

        // 创建会话
        $conversation = Conversations::create([
            'type' => $type,
            'name' => $data['name'] ?? '',
            'avatar' => $data['avatar'] ?? '',
            'target_id' => $type === 1 ? $targetId : null,
            'is_active' => 1
        ]);

        // 创建会话成员
        ConversationMembers::create([
            'conversation_id' => $conversation->id,
            'user_id' => $userId,
            'role' => 3 // 创建者默认群主
        ]);

        if ($type === 1) {
            ConversationMembers::create([
                'conversation_id' => $conversation->id,
                'user_id' => $targetId,
                'role' => 1
            ]);
        }

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
        // 获取请求参数
        $data = $request->get() + ['user_id' => $request->user['id']];

        $this->validate($data, ConversationValidate::class, 'list');

        $userId = $data['user_id'];
        $page = (int)($data['page'] ?? 1);
        $limit = (int)($data['limit'] ?? 20);

        // 关联 conversation 和 conversation->members->user，避免 N+1
        $query = ConversationMembers::with([
            'conversation' => function ($q) {
                $q->where('is_active', 1)
                    ->with(['members.user']);
            }
        ])->where('user_id', $userId);

        // 分页
        $members = $query->page($page, $limit)->select();

        $list = [];

        foreach ($members as $member) {
            $conv = $member->conversation;
            if (!$conv) continue;

            // 获取最后一条消息（通过子查询优化）
            $lastMessage = Messages::where('conversation_id', $conv->id)
                ->order('id', 'desc')
                ->find();

            $client_id = $lastMessage->id ?? '';
            $lastContent = $lastMessage->content ?? '';
            $send_time = $lastMessage->created_at ?? '';
            $message_type = $lastMessage->type ?? 1;
            $status = $lastMessage->status ?? 1;
            $send_id = $this->idToUuid($lastMessage->from_user_id);
            $send_name = User::where('id', $send_id)->value('nickname');
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

        // 返回分页信息
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
                $query->table((new MessageUserDeleted())->getTable()) // 使用模型类自动获取表名
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

        return $this->success([
            'conversation_id' => $conversationId,
            'messages' => $formatMessages
        ]);
    }
}
