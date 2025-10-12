<?php

namespace plugin\leonim\gateway\handler;

use GatewayWorker\Lib\Gateway;
use plugin\leonim\app\model\User;
use plugin\leonim\gateway\Response;

class FriendHandler
{
    /**
     * 处理好友请求推送
     */
    public static function request(string $client_id, array $data): void
    {
        $fromUser = Gateway::getUidByClientId($client_id);
        $toUser = $data['to'] ?? null;
        $remark = $data['remark'] ?? '';
        echo "好友申请：{$toUser} -- { $remark }\n";
        if (!$toUser) {
            Gateway::sendToClient($client_id, Response::fail('Missing to_uid'));
            return;
        }

        // 对方在线推送
        if (Gateway::isUidOnline($toUser)) {
            $user = User::where('uuid', $fromUser)->find();
            Gateway::sendToUid($toUser, Response::ok('message', [
                'from_uid' => $user->uuid,
                'from_nickname' => $user->nickname,
                'from_avatar' => $user->avatar,
                'remark' => $remark,
                'type' => 'friend_request',
                'time' => date('Y-m-d H:i:s'),
            ]));
        }

        // 自己确认
        Gateway::sendToClient($client_id, Response::ok('friendRequestSent', [
            'to_uid' => $toUser,
            'status' => Gateway::isUidOnline($toUser) ? 'delivered' : 'queued'
        ]));
    }
}
