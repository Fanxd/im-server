<?php
namespace plugin\leonim\gateway;

use Tinywan\Jwt\Exception\JwtTokenException;
use Tinywan\Jwt\Exception\JwtTokenExpiredException;
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
     * 验证 token 是否有效，并返回 token 中的用户标识
     * @param string $token
     * @return string|false 返回用户 uuid 或 userId，验证失败返回 false
     */
    public static function verifyToken(string $token): bool|string
    {
        if (empty($token)) {
            return false;
        }

        try {
            $verify = JwtToken::verify(1, $token);
        } catch (JwtTokenExpiredException $e) {
            // token 过期，返回 false
            return false;
        } catch (JwtTokenException $e) {
            // 其他 jwt 异常，返回 false
            return false;
        }
        if (empty($verify)) {
            return false;
        }

        return $verify['extend']['uuid'] ?? $verify['userId'] ?? false;
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
