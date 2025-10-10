<?php
namespace plugin\leonim\gateway;

use Tinywan\Jwt\Exception\JwtTokenException;
use Tinywan\Jwt\JwtToken;

/**
 * Class Auth
 * 管理客户端鉴权状态
 */
class Auth
{
    /**
     * 记录客户端是否已完成鉴权
     * @var array
     */
    protected static array $clientAuthStatus = [];

    /**
     * 验证 token 是否有效
     * @param string $token
     * @param string $userID
     * @return bool
     */
    public static function verifyToken(string $token, string $userID): bool
    {
        if (empty($token) || empty($userID)) {
            return false;
        }

         $verify = JwtToken::verify(1, $token);

        if (empty($verify)) {
           return false;
        }else {
            return $verify['extend']['uuid'] === $userID;
        }
    }

    /**
     * 标记客户端已鉴权
     * @param string $client_id
     */
    public static function setAuth(string $client_id): void
    {
        self::$clientAuthStatus[$client_id] = true;
    }

    /**
     * 检查客户端是否已鉴权
     * @param string $client_id
     * @return bool
     */
    public static function isAuth(string $client_id): bool
    {
        return !empty(self::$clientAuthStatus[$client_id]);
    }

    /**
     * 移除客户端鉴权状态（断开连接时）
     * @param string $client_id
     */
    public static function removeAuth(string $client_id): void
    {
        unset(self::$clientAuthStatus[$client_id]);
    }
}
