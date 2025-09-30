<?php
namespace plugin\leonim\app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        // 如果是 OPTIONS 预检请求，直接返回响应并写入 CORS 头
        if ($request->method() === 'OPTIONS') {
            $response = new Response(204);
        } else {
            $response = $next($request);
        }

        $origin = $request->header('origin', '*');

        return $response->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With',
        ]);
    }
}
