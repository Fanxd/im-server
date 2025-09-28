<?php

namespace plugin\leonim\app\model;

use think\model\relation\HasMany;
use think\model\relation\BelongsTo;
class Messages extends Base
{
    protected string $table = 'wa_messages';

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversations::class, 'conversation_id', 'id');
    }

    // 被哪些用户删除了（个人视图删除）
    public function deletedUsers(): HasMany
    {
        return $this->hasMany(MessageUserDeleted::class, 'message_id', 'id');
    }

    // 发送者
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
