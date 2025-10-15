<?php

namespace plugin\leonim\app\middleware;

use Tinywan\Jwt\Exception\JwtTokenException;
use Tinywan\Jwt\JwtToken;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class JwtAuthMiddleware implements MiddlewareInterface
{
    // 允许不验证 Token 的方法
    protected array $exceptActions = [
        'login', 'register' // 登录接口不拦截
    ];

    public function process(Request $request, callable $next): Response
    {
        // 当前请求方法名 (login / info / logout ...)
        $action = $request->action ?? '';

        // 放行无需鉴权的方法
        if (in_array($action, $this->exceptActions, true)) {
            return $next($request);
        }

        try {
            // 从 header 中读取 Authorization
            $authorization = $request->header('Authorization');
            if (!$authorization) {
                throw new JwtTokenException('请求未携带 token');
            }

            // Bearer token 格式处理
            if (preg_match('/^Bearer\s+(.*?)$/i', $authorization, $matches)) {
                $token = $matches[1];
            } else {
                throw new JwtTokenException('无效的 token 格式');
            }

            // 使用 verify 方法
            $result = JwtToken::verify(1, $token);

            // 返回user信息
            $payload = $result['extend'] ?? [];

            if (empty($payload)) {
                throw new JwtTokenException('Token 解析失败');
            }

            // ✅ 注入 request->user
            $request->user = $payload;

            return $next($request);

        } catch (JwtTokenException $e) {
            // 交给统一异常处理器（推荐）或直接返回 JSON
            return json([
                'code' => 401,
                'msg' => $e->getMessage(),
            ]);
        }
    }
}
