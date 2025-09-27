<?php

namespace plugin\leonim\app\controller;

use support\Request;
use support\Response;
use Tinywan\Validate\Exception\ValidateException;

/**
 * 基础控制器
 */
class Base
{

    /**
     * @param string $msg
     * @param array $data
     * @return Response
     */
    protected function success( array $data = [], string $msg = '成功'): Response
    {
        return $this->json(0, $msg, $data);
    }

    /**
     * 返回格式化json数据
     *
     * @param int $code
     * @param string $msg
     * @param array $data
     * @return Response
     */
    protected function json(int $code, string $msg = 'ok', array $data = []): Response
    {
        return json(['code' => $code, 'data' => $data, 'msg' => $msg]);
    }

    /**
     * @param string $msg
     * @param array $data
     * @return Response
     */
    protected function fail(string $msg = '失败', array $data = []): Response
    {
        return $this->json(1, $msg, $data);
    }


    /**
     * 批量接收 POST 参数
     *
     * @param Request $request
     * @param array $fields
     * @return array
     */
    protected function input(Request $request, array $fields): array
    {
        $post = $request->post();
        $result = [];
        foreach ($fields as $field => $default) {
            if (is_int($field)) {
                $field = $default;
                $default = '';
            }
            $result[$field] = $post[$field] ?? $default;
        }
        return $result;
    }

    /**
     * 使用 validate 验证
     */
    protected function validate(array $data, string $validateClass, string $scene = ''): void
    {
        $validate = new $validateClass();
        if ($scene) {
            $validate->scene($scene);
        }
        if (!$validate->check($data)) {
            throw new ValidateException($validate->getError());
        }
    }
}
