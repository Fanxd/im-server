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
            'uuid' => $user->uuid,
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
            'uuid' => $user['uuid'],
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

    /**
     * 用户注册
     * @param Request $request
     * @return Response
     */
    public function register(Request $request): Response
    {
        // 获取参数
        $data = $this->input($request, ['username' => '', 'password' => '']);

        // 参数验证
        $this->validate($data, AccountValidate::class, 'register');

        // 生成默认 uuid、nickname、status=0
        $uuid = uniqid();
        $nickname = $data['username'];
        $status = 0;
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT); // 密码加密

        // 保存用户到数据库
        $user = new User();
        $user->uuid = $uuid;
        $user->username = $data['username'];
        $user->nickname = $nickname;
        $user->password = $passwordHash;
        $user->status = $status;
        $user->save();

        // 返回成功信息及用户基本信息
        $info = [
            'uuid' => $uuid,
            'username' => $data['username'],
            'nickname' => $nickname
        ];
        return $this->success($info, '注册成功');
    }

}


