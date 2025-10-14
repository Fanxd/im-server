<?php

use LeonIm\ImServer\BusinessWorker;
use LeonIm\ImServer\Gateway;
use LeonIm\ImServer\Register;

return [
    // 网关进程配置
    'gateway' => [
        'handler' => Gateway::class,
        'listen' => 'websocket://0.0.0.0:2346',
        'count' => 2,
        'reloadable' => false,
        'constructor' => [
            'config' => [
                'lanIp' => '127.0.0.1',
                'startPort' => 2300,
                'pingInterval' => 30, // 缩短心跳间隔用于测试心跳功能
                'pingData' => '{"action":"ping"}',
                'registerAddress' => '127.0.0.1:1113',
                'onConnect' => function () {
                    // 客户端连接时的回调
                }
            ]
        ]
    ],

    // 业务工作进程配置
    'worker' => [
        'handler' => BusinessWorker::class,
        'count' => cpu_count() * 2,
        'constructor' => [
            'config' => [
                'eventHandler' => \plugin\leonim\gateway\Events::class,
                'name' => 'ChatBusinessWorker',
                'registerAddress' => '127.0.0.1:1113'
            ]
        ]
    ],

    // 注册服务进程配置
    'register' => [
        'handler' => Register::class,
        'listen' => 'text://127.0.0.1:1113',
        'count' => 1, // 必须为1
        'reloadable' => false,
        'constructor' => []
    ]
];
