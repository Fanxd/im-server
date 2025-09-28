<?php

namespace plugin\leonim\app\model;

use think\model\relation\BelongsTo;

class FriendRequests extends Base
{
    protected string $table = 'wa_friend_requests';

    protected $pk = 'id';

    /**
     * 关联申请人用户信息
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id', 'uuid');
    }

    /**
     * 关联接收人用户信息
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id', 'uuid');
    }

}
