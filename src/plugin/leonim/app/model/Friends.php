<?php

namespace plugin\leonim\app\model;

use think\model\relation\BelongsTo;

class Friends extends Base
{
    protected string $table = 'wa_friends';

    protected $pk = 'id';

    // 好友用户信息关联
    public function friendInfo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id', 'id');
    }

    // 关联用户表
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id', 'id');
    }
}
