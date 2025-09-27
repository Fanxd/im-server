<?php
namespace plugin\leonim\app\controller;

use plugin\admin\app\model\User;
use plugin\leonim\app\model\FriendRequests;
use plugin\leonim\app\model\Friends;
use plugin\leonim\app\validate\FriendValidate;
use support\Request;
use support\Response;
use Tinywan\Jwt\JwtToken;

class FriendController extends Base
{
    public function add(Request $request): Response
    {
        // 批量接收参数
        $data = $this->input($request, ['friend_id', 'message' => '']);

        // 参数验证
        $this->validate($data, FriendValidate::class, 'add');

        $userId = JwtToken::getCurrentId();
        $friendId = (int)$data['friend_id'];

        // 是否已有未处理申请
        if (FriendRequests::where('from_user_id', $userId)
            ->where('to_user_id', $friendId)
            ->where('status', 0)
            ->exists()) {
            return $this->fail('好友请求已发送，请等待对方处理');
        }

        FriendRequests::create([
            'from_user_id' => $userId,
            'to_user_id'   => $friendId,
            'message'      => $data['message'],
            'status'       => 0,
            'is_read'      => 0
        ]);

        return $this->success([], '好友请求已发送');
    }

    public function delete(Request $request): Response
    {
        $friendId = $request->post('friend_id', 0);

        // TODO: 删除好友逻辑

        return json(['code'=>0, 'msg'=>'好友已删除']);
    }

    public function accept(Request $request): Response
    {
        $requestId = $request->post('request_id', 0);

        // TODO: 同意好友申请逻辑

        return json(['code'=>0, 'msg'=>'已同意好友申请']);
    }

    public function reject(Request $request): Response
    {
        $requestId = $request->post('request_id', 0);

        // TODO: 拒绝好友申请逻辑

        return json(['code'=>0, 'msg'=>'已拒绝好友申请']);
    }

    public function requests(Request $request): Response
    {
        // TODO: 查询好友申请列表
        $data = [];

        return json(['code'=>0, 'msg'=>'获取成功', 'data'=>$data]);
    }

    public function unreadCount(Request $request): Response
    {
        // TODO: 查询未读好友申请数量
        $count = 0;

        return json(['code'=>0, 'msg'=>'获取成功', 'data'=>['unread_count'=>$count]]);
    }

    public function list(Request $request): Response
    {
        // TODO: 查询好友列表
        $friends = [];

        return json(['code'=>0, 'msg'=>'获取成功', 'data'=>$friends]);
    }

    public function blacklist(Request $request): Response
    {
        // TODO: 查询黑名单列表
        $blacklist = [];

        return json(['code'=>0, 'msg'=>'获取成功', 'data'=>$blacklist]);
    }

    public function addToBlacklist(Request $request): Response
    {
        $friendId = $request->post('friend_id', 0);

        // TODO: 添加到黑名单逻辑

        return json(['code'=>0, 'msg'=>'已加入黑名单']);
    }

    public function removeFromBlacklist(Request $request): Response
    {
        $friendId = $request->post('friend_id', 0);

        // TODO: 从黑名单移除逻辑

        return json(['code'=>0, 'msg'=>'已移出黑名单']);
    }
}
