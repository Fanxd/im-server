<?php

namespace plugin\leonim\app\validate;

use Tinywan\Validate\Validate;
use plugin\leonim\app\model\User;

class AccountValidate extends Validate
{
    protected array $rule = [
        'username' => 'require|length:3,20',
        'nickname' => 'require',
        'password' => 'require|length:6,32',
        'email' => 'email',
        'mobile' => 'regex:/^\d{10,15}$/',
    ];

    protected array $message = [
        'username.require' => '用户名不能为空',
        'username.length' => '用户名长度必须在3-20之间',
        'username.unique' => '用户名已存在',
        'nickname.require' => '昵称不能为空',
        'password.require' => '密码不能为空',
        'password.length' => '密码长度必须在6-32之间',
        'email.email' => '邮箱格式不正确',
        'mobile.regex' => '手机号格式不正确',
    ];

    protected array $scene = [
        'login' => ['username', 'password'],
        'update' => ['nickname', 'email', 'mobile'],
        'password' => ['password'],
        'register' => ['username' => 'require|length:3,20|checkUsernameUnique', 'password'],
    ];

    /**
     * 检查用户名是否唯一
     * @param $value
     * @return bool|string
     */
    protected function checkUsernameUnique($value): bool|string
    {
        if (User::where('username', $value)->count() > 0) {
            return '用户名已存在';
        }
        return true;
    }
}


