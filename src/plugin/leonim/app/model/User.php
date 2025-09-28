<?php

namespace plugin\leonim\app\model;

class User extends Base
{
    protected string $table = 'wa_users';

    protected $pk = 'id';

    public static function uuidToId(string $uuid): ?int
    {
        return self::where('uuid', $uuid)->value('id');
    }
}
