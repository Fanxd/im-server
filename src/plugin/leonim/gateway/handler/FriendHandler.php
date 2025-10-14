<?php
namespace plugin\leonim\gateway\handler;

use GatewayWorker\Lib\Gateway;
use plugin\leonim\app\model\User;
use plugin\leonim\app\model\FriendRequests;
use plugin\leonim\gateway\Response;

class FriendHandler
{

    /**
     * 处理好友请求推送
     * @param string $client_id
     * @param array $data
     * @param $requestId
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public static function handleFriendRequest(string $client_id, array $data, $requestId): void
    {
        // 获取发送好友请求的用户ID
        $fromUser = Gateway::getUidByClientId($client_id);

        // 获取接收好友请求的用户ID
        $toUser = $data['toUser'] ?? null;

        // 获取备注信息
        $remark = $data['remark'] ?? '';

        // 输出日志，方便调试
        echo sprintf("好友申请发送者: %s，接收者: %s，备注: %s\n", $fromUser, $toUser, $remark);

        // 如果接收用户ID不存在，返回错误信息
        if (!$toUser) {
            Gateway::sendToClient($client_id, Response::make(
                'sendFriendRequest',
                $requestId,
                400,
                '缺少接收方用户ID',
                []
            ));
            return;
        }

        // 发送方不能给自己发送好友请求
        if ($fromUser === $toUser) {
            Gateway::sendToClient($client_id, Response::make(
                'sendFriendRequest',
                $requestId,
                400,
                '不能给自己发送好友请求',
                []
            ));
            return;
        }

        // 检查是否已存在未处理的好友请求，防止重复发送
        $existRequest = FriendRequests::where([
            ['from_user_id', '=', $fromUser],
            ['to_user_id', '=', $toUser],
            ['status', '=', 0],
        ])->find();
        if ($existRequest) {
            // 已有未处理的好友请求，直接返回错误
            Gateway::sendToClient($client_id, Response::make(
                'sendFriendRequest',
                $requestId,
                409,
                '已发送过好友请求，请勿重复发送',
                []
            ));
            return;
        }

        // 查询发送请求的用户信息
        $user = User::where('uuid', $fromUser)->find();

        // 如果发送方用户不存在，返回错误信息
        if (!$user) {
            Gateway::sendToClient($client_id, Response::make(
                'sendFriendRequest',
                $requestId,
                404,
                '发送方用户不存在',
                []
            ));
            return;
        }

        // 查询接收请求的用户信息
        $toUserInfo = User::where('uuid', $toUser)->find();

        // 如果接收方用户不存在，返回错误信息
        if (!$toUserInfo) {
            Gateway::sendToClient($client_id, Response::make(
                'sendFriendRequest',
                $requestId,
                404,
                '接收方用户不存在',
                []
            ));
            return;
        }

        // 判断接收用户是否在线，如果在线则推送好友请求消息
        if (Gateway::isUidOnline($toUser)) {
            Gateway::sendToUid($toUser, Response::make(
                'sendFriendRequest',
                $requestId,
                200,
                '好友请求',
                [
                    'fromUserId' => $user->uuid,
                    'fromNickname' => $user->nickname,
                    'fromAvatar' => $user->avatar,
                    'remark' => $remark,
                    'time' => date('Y-m-d H:i:s'),
                ]
            ));
        }

        // 给发送请求的客户端确认发送状态，状态根据接收方是否在线决定
        Gateway::sendToClient($client_id, Response::make(
            'friendRequestConfirmation',
            $requestId,
            200,
            '好友请求已发送',
            [
                'toUserId' => $toUser,   // 接收方用户ID
                'status' => Gateway::isUidOnline($toUser) ? 'delivered' : 'queued', // 在线为delivered，离线为queued
            ]
        ));
    }
}
