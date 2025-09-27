<?php

use LeonIm\ImServer\BusinessWorker;
use LeonIm\ImServer\Gateway;
use LeonIm\ImServer\Register;

return [
    'gateway' => [
        'handler' => Gateway::class,
        'listen' => 'websocket://0.0.0.0:2346',
        'count' => 2,
        'reloadable' => false,
        'constructor' => ['config' => [
            'lanIp' => '127.0.0.1',
            'startPort' => 2300,
            'pingInterval' => 25,
            'pingData' => '{"type":"ping"}',
            'registerAddress' => '127.0.0.1:1113',
            'onConnect' => function () {
            },
        ]]
    ],
    'worker' => [
        'handler' => BusinessWorker::class,
        'count' => cpu_count() * 2,
        'constructor' => ['config' => [
            'eventHandler' => \plugin\leonim\gateway\Events::class,
            'name' => 'ChatBusinessWorker',
            'registerAddress' => '127.0.0.1:1113',
        ]]
    ],
    'register' => [
        'handler' => Register::class,
        'listen' => 'text://127.0.0.1:1113',
        'count' => 1, // Must be 1
        'reloadable' => false,
        'constructor' => []
    ],
];
