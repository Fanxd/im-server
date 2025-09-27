<?php

use plugin\leonim\app\controller\AccountController;
use plugin\leonim\app\controller\FriendController;
use plugin\leonim\app\controller\UserController;
use plugin\leonim\app\middleware\JwtAuthMiddleware;
use Webman\Route;

// Leon IM 相关路由
Route::group('/app/im', function () {
    // ------------------------
    // 登入
    // ------------------------
    Route::post('/login', [AccountController::class, 'login']);

    // ------------------------
    // 用户接口
    // ------------------------
    Route::group('/user', function () {
        Route::get('/info', [AccountController::class, 'info']);             // 获取当前用户信息
        Route::post('/logout', [AccountController::class, 'logout']);        // 退出登录
        Route::post('/profile/update', [AccountController::class, 'update']); // 更新用户资料
        Route::post('/password', [AccountController::class, 'password']);    // 修改密码
        Route::post('/search', [UserController::class, 'search']); // 搜索用户
    });

    // ------------------------
    // 好友接口
    // ------------------------
    Route::group('/friend', function () {
        Route::post('/add', [FriendController::class, 'add']);                   // 添加好友
        Route::post('/delete', [FriendController::class, 'delete']);             // 删除好友
        Route::post('/accept', [FriendController::class, 'accept']);             // 同意好友申请
        Route::post('/reject', [FriendController::class, 'reject']);             // 拒绝好友申请
        Route::get('/requests', [FriendController::class, 'requests']);          // 获取好友申请列表
        Route::get('/requests/unread', [FriendController::class, 'unreadCount']); // 获取好友申请未读数量
        Route::get('/list', [FriendController::class, 'list']);                  // 获取好友列表
    });

    // ------------------------
    // 黑名单接口
    // ------------------------
    Route::group('/blacklist', function () {
        Route::get('/list', [FriendController::class, 'blacklist']);             // 获取黑名单列表
        Route::post('/add', [FriendController::class, 'addToBlacklist']);       // 加入黑名单
        Route::post('/remove', [FriendController::class, 'removeFromBlacklist']); // 从黑名单移除
    });
})->middleware([
    JwtAuthMiddleware::class
]);

