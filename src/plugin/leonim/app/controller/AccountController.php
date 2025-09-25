<?php

namespace plugin\leonim\app\controller;

use plugin\leonim\app\model\User;
use support\Request;
use support\Response;
use Tinywan\Jwt\JwtToken;

/**
 * 用户鉴权
 */
class AccountController extends Base
{

    /**
     * 登录
     */
    public function login(Request $request): Response
    {
        $username = $request->post('username');
        $password = $request->post('password');

        $user = User::where('username', $username)->first();
        if (!$user || !password_verify($password, $user->password)) {
            return json(['code' => 401, 'msg' => '用户名或密码错误']);
        }

        // 生成 token
        $token = JwtToken::generateToken([
            'uid' => $user->id,
            'username' => $user->username
        ]);

        return json(['code' => 0, 'msg' => '登录成功', 'token' => $token]);
    }

    /**
     * 退出
     */
    public function logout(Request $request): Response
    {

    }

    /**
     * 获取登录信息
     */
    public function info(Request $request): Response
    {

    }

    /**
     * 更新
     */
    public function update(Request $request): Response
    {

    }

    /**
     * 修改密码
     */
    public function password(Request $request): Response
    {

    }

}
