<?php

namespace plugin\leonim\app\model;

use think\model\relation\HasMany;
use think\model\relation\BelongsTo;
class Conversations extends Base
{
    protected string $table = 'wa_conversations';

    // 会话成员
    public function members(): HasMany
    {
        return $this->hasMany(ConversationMembers::class, 'conversation_id', 'id');
    }

    // 单聊目标用户
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id', 'id');
    }

    // 会话消息
    public function messages(): HasMany
    {
        return $this->hasMany(Messages::class, 'conversation_id', 'id');
    }
}
