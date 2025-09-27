<?php

namespace plugin\leonim\app\exception;

use Illuminate\Validation\ValidationException;
use support\Request;
use Tinywan\ExceptionHandler\Handler as ErrorHandler;
use Webman\Http\Response;
use Tinywan\Jwt\Exception\JwtTokenException;

class Handler extends ErrorHandler
{
    public function render(Request|\Webman\Http\Request $request, \Throwable $exception): Response
    {
        if ($exception instanceof JwtTokenException) {
            return json([
                'code' => 401,
                'message' => $exception->getMessage(),
            ]);
        }

        // 其它异常继续走默认处理
        return parent::render($request, $exception);
    }
}
