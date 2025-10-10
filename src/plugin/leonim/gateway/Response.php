<?php
namespace plugin\leonim\gateway;

/**
 * 统一返回格式处理
 */
class Response
{
    /**
     * ✅ 成功响应
     * @param string $event
     * @param array|null $data
     * @param string $message
     * @return string
     */
    public static function ok(string $event, array $data = null, string $message = 'success'): string
    {
        return self::format($event, $data, 0, $message);
    }

    /**
     * ✅ 失败响应
     * @param string $event
     * @param string $message
     * @param int $code
     * @param array|null $data
     * @return string
     */
    public static function fail(string $message = 'error', int $code = 400, array $data = null, string $event = 'error'): string
    {
        return self::format($event, $data, $code, $message);
    }

    /**
     * ✅ 统一格式封装
     * @param string $event
     * @param array|null $data
     * @param int $code
     * @param string $message
     * @return string
     */
    public static function format(string $event, array $data = null, int $code = 0, string $message = ''): string
    {
        $response = [
            'event'   => $event,
            'code'    => $code,
            'message' => $message,
            'data'    => $data ?? new \stdClass(),
        ];

        return json_encode($response, JSON_UNESCAPED_UNICODE);
    }
}
