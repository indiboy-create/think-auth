<?php

declare(strict_types=1);

namespace youvision\auth;

use youvision\auth\service\Auth as AuthService;

/**
 * 权限认证静态入口
 *
 * @method static bool  check(string|array $name, int $uid, int $type = 1, string $mode = 'url', string $relation = 'or')
 * @method static array rules(int $uid, int $type = 1)
 * @method static mixed roles(int $uid, string $field = '')
 * @method static bool  hasRole(int $uid, int|string $role)
 * @method static bool  hasAnyRole(int $uid, array $roles)
 * @method static bool  hasAllRoles(int $uid, array $roles)
 * @method static void  clearCache(int $uid, int $type = 1)
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
