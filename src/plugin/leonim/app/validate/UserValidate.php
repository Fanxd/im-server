<?php

namespace LeonIm\ImServer\leonim\app\validate;

use Tinywan\Validate\Validate;

class UserValidate extends Validate
{
    protected array $rule = [
        'keyword' => 'require|max:50',
    ];

    protected array $scene = [
        'search' => ['keyword'],
    ];

    protected array $message = [
        'keyword.require' => '搜索关键字不能为空',
        'keyword.max'     => '搜索关键字不能超过50个字符',
    ];
}
