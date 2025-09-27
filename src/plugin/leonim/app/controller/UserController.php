<?php
namespace LeonIm\ImServer\leonim\app\controller;

use LeonIm\ImServer\leonim\app\model\User;
use LeonIm\ImServer\leonim\app\validate\UserValidate;
use support\Request;
use support\Response;
use Tinywan\Jwt\JwtToken;

class UserController extends Base
{
    /**
     * 搜索用户
     */
    public function search(Request $request): Response
    {
        // 批量接收参数
        $data = $this->input($request, ['keyword']);

        // 参数验证
        $this->validate($data, UserValidate::class, 'search');

        $keyword = $data['keyword'];

        $userId = JwtToken::getCurrentId();

        // 模糊搜索 username / nickname / mobile，并排除自己
        $users = User::where('id', '<>', $userId)
            ->where(function ($query) use ($keyword) {
                $query->where('username', 'like', "%{$keyword}%")
                    ->whereOr('nickname', 'like', "%{$keyword}%")
                    ->whereOr('mobile', 'like', "%{$keyword}%");
            })
            ->field('username,nickname,avatar,mobile')
            ->limit(50)
            ->select()->toArray();

        return $this->success($users);
    }
}
