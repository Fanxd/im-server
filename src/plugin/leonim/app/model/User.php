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

    public static function idToUuid($id)
    {
        return self::where('id', $id)->value('uuid');
    }

    public function getAvatarAttr($value)
    {
        if (!str_contains($value, 'http') && !str_contains($value, 'https')) {
            $value = config('process.webman.listen').$value;
        }

        return $value;
    }
}
