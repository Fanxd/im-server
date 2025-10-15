<?php

namespace plugin\leonim\app\validate;

use Tinywan\Validate\Validate;

class AccountValidate extends Validate
{
    /**
     * 基础验证规则
     */
    protected array $rule = [
        'username' => 'require|length:3,20', // 用户名必填，长度3-20
        'nickname' => 'require',              // 昵称必填
        'password' => 'require|length:6,32',  // 密码必填，长度6-32
        'email' => 'email',                // 邮箱格式验证
        'mobile' => 'regex:/^\d{10,15}$/'  // 手机号正则验证
    ];

    /**
     * 错误提示信息
     */
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

    /**
     * 场景验证
     */
    protected array $scene = [
        'login' => ['username', 'password'],   // 登录只验证用户名和密码
        'update' => ['nickname', 'email', 'mobile'], // 更新用户信息验证
        'password' => ['password'],              // 修改密码验证
        'register' => ['username', 'password'] // 注册验证
    ];

}
