<?php

namespace plugin\leonim\app\model;

use think\model\relation\BelongsTo;
use think\model\relation\HasOne;
class ConversationMembers extends Base
{
    protected string $table = 'wa_conversation_members';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }


    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversations::class, 'conversation_id', 'id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Messages::class, 'conversation_id', 'conversation_id')
            ->order('id', 'desc');
    }
}
