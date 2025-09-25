<?php
/**
 * leonim/im-server 插件路由
 */
use Webman\Route;
use support\Request;

// 分组写法
Route::group('/im', function () {

    Route::any('/test', function (Request $request) {
        return response('test');
    });

    /*Route::post('/login', [AuthController::class, 'login']);

    // 需要 JWT 认证的接口
    Route::get('/me', [AuthController::class, 'me'])
        ->middleware([JwtMiddleware::class]);*/
});
