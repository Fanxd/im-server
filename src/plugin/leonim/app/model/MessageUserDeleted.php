<?php

namespace plugin\leonim\app\model;

use think\model\relation\BelongsTo;

class MessageUserDeleted extends Base
{
    protected $name = 'wa_message_user_deleted';
    protected string $autoWriteTimestamp = 'datetime';

    protected string $createTime = 'deleted_at';
    protected bool $updateTime = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Messages::class, 'message_id', 'id');
    }
}
