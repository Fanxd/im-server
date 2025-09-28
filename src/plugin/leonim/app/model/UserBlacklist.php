<?php

namespace plugin\leonim\app\model;

class UserBlacklist extends Base
{
    protected $table = 'wa_user_blacklist';

    // 关联被拉黑的用户信息
    public function blockedUser()
    {
        return $this->belongsTo(User::class, 'blocked_user_id', 'id');
    }

}
