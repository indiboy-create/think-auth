<?php

declare(strict_types=1);

namespace youvision\auth;

use youvision\auth\service\Auth as AuthService;

/**
 * 权限认证静态入口
 *
 * @method static bool check(string|array $name, int $uid, int $type = 1, string $mode = 'url', string $relation = 'or')
 * @method static array rules(int $uid, int $type = 1)
 * @method static mixed roles(int $uid, string $field = '')
 *
 * @see \youvision\auth\service\Auth
 */
class Auth
{
    /**
     * 转发静态调用到认证服务
     *
     * @param string $method
     * @param array  $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        return app(AuthService::class)->{$method}(...$args);
    }
}