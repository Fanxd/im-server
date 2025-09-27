<?php

namespace plugin\leonim\app\controller;

use plugin\leonim\app\model\User;
use plugin\leonim\app\validate\AccountValidate;
use support\Request;
use support\Response;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use Tinywan\Jwt\JwtToken;

/**
 * 用户鉴权
 */
class AccountController extends Base
{

    /**
     * 登入
     * @param Request $request
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function login(Request $request): Response
    {
        $data = $this->input($request, ['username'=>'','password'=>'']);

        $this->validate($data, AccountValidate::class, 'login');

        $user = User::where('username', $data['username'])->find();

        if (!$user || !password_verify($data['password'], $user->password)) {
            return json(['code' => 401, 'msg' => '用户名或密码错误']);
        }

        if ($user->status != 0) {
            return $this->fail('当前账户暂时无法登录');
        }

        $user->last_time = date('Y-m-d H:i:s');
        $user->save();

        // 生成 token
        $token = JwtToken::generateToken([
            'id' => $user->id,
            'username' => $user->username
        ]);

        return $this->success($token, '登入成功');
    }

    /**
     * 退出
     * @return Response
     */
    public function logout(): Response
    {
        JwtToken::clear();

        return $this->success();
    }

    /**
     * 获取登录信息
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function info(): Response
    {
        $user = User::where('id', JwtToken::getCurrentId())->find();

        $info = [
            'username' => $user['username'],
            'nickname' => $user['nickname'],
            'avatar' => $user['avatar'],
            'email' => $user['email'],
            'mobile' => $user['mobile']
        ];

        return $this->success($info);
    }

    /**
     * 更新个人资料
     * @param Request $request
     * @return Response
     * @throws DbException
     */
    public function update(Request $request): Response
    {
        $data = $this->input($request, ['nickname' => '', 'email' => '', 'mobile' => '']);

        $this->validate($data, AccountValidate::class, 'update');

        User::where('id', Jwttoken::getCurrentId())->update($data);

        return $this->success($data);
    }

    /**
     * 修改密码
     * @param Request $request
     * @return Response
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function password(Request $request): Response
    {
        $data = $this->input($request, ['password'=>'']);

        $this->validate($data, AccountValidate::class, 'password');

        $user = User::find(JwtToken::getCurrentId());

        $user->password = password_hash($data['password'], PASSWORD_DEFAULT);

        $user->save();

        return $this->success($data);
    }

}
