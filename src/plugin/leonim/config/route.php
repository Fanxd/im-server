<?php

use plugin\leonim\app\controller\AccountController;
use plugin\leonim\app\middleware\JwtAuthMiddleware;
use Webman\Route;

// Leon IM 相关路由
Route::group('/app/im', function () {
    // 登录
    Route::post('/login', [AccountController::class, 'login']);
    // 用户模块
    Route::group('/user', function () {
        $controller = AccountController::class;
        Route::get('/info', [$controller, 'info']);             // 获取当前用户信息
        Route::post('/logout', [$controller, 'logout']);        // 退出登录
        Route::post('/profile/update', [$controller, 'update']); // 更新用户资料
        Route::post('/password', [$controller, 'password']);    // 修改密码
    });
})->middleware([
    JwtAuthMiddleware::class
]);

