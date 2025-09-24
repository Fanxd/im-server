<?php

namespace plugin\leonim\app\model;

class User extends Base
{
    protected $table = 'users';
    protected $fillable = ['username', 'password'];
}
