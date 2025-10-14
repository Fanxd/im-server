<?php
namespace plugin\leonim\gateway;

/**
 * 统一返回格式处理
 */
class Response
{
    /**
     * 构建统一 WebSocket 消息格式
     * @param string $action
     * @param string|null $requestId
     * @param int $code
     * @param string $message
     * @param array $data
     * @return string
     */
    public static function make(string $action, ?string $requestId, int $code, string $message, array $data = []): string
    {
        $response = [
            'action' => $action,
            'requestId' => $requestId,
            'code' => $code,
            'message' => $message,
            'data' => $data ?? new \stdClass(),
        ];
        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
