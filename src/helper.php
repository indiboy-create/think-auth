<?php

declare(strict_types=1);

if (!function_exists('auth_check')) {
    /**
     * 权限认证快捷方法
     *
     * @param string|array $name     需要验证的规则，支持逗号分隔字符串或数组
     * @param int          $uid      用户 ID
     * @param int          $type     认证类型
     * @param string       $mode     认证模式，url 表示同时校验规则中的请求参数
     * @param string       $relation 多规则关系：or 满足其一即通过，and 需全部满足
     * @return bool
     */
    function auth_check($name, int $uid, int $type = 1, string $mode = 'url', string $relation = 'or'): bool
    {
        return \youvision\auth\Auth::check($name, $uid, $type, $mode, $relation);
    }
}

if (!function_exists('get_auth_rules')) {
    /**
     * 获取用户拥有的全部规则名称
     *
     * @param int $uid  用户 ID
     * @param int $type 认证类型
     * @return array
     */
    function get_auth_rules(int $uid, int $type = 1): array
    {
        return \youvision\auth\Auth::rules($uid, $type);
    }
}

if (!function_exists('get_auth_role_ids')) {
    /**
     * 获取用户所属的全部角色 ID
     *
     * @param int $uid 用户 ID
     * @return array
     */
    function get_auth_role_ids(int $uid): array
    {
        return array_map('intval', (array) \youvision\auth\Auth::roles($uid, 'id'));
    }
}

if (!function_exists('get_auth_roles')) {
    /**
     * 获取用户所属的全部角色数据
     *
     * @param int $uid 用户 ID
     * @return mixed
     */
    function get_auth_roles(int $uid)
    {
        return \youvision\auth\Auth::roles($uid);
    }
}

if (!function_exists('auth_has_role')) {
    /**
     * 判断是否拥有指定角色（ID 或角色名）
     *
     * @param int|string $role
     */
    function auth_has_role(int $uid, $role): bool
    {
        return \youvision\auth\Auth::hasRole($uid, $role);
    }
}

if (!function_exists('auth_has_any_role')) {
    /**
     * 判断是否拥有角色列表中的任意一个
     *
     * @param array<int, int|string> $roles
     */
    function auth_has_any_role(int $uid, array $roles): bool
    {
        return \youvision\auth\Auth::hasAnyRole($uid, $roles);
    }
}

if (!function_exists('auth_has_all_roles')) {
    /**
     * 判断是否同时拥有角色列表中的全部角色
     *
     * @param array<int, int|string> $roles
     */
    function auth_has_all_roles(int $uid, array $roles): bool
    {
        return \youvision\auth\Auth::hasAllRoles($uid, $roles);
    }
}

if (!function_exists('auth_clear_cache')) {
    /**
     * 清理指定用户 auth_type=2 的 Session 规则缓存
     */
    function auth_clear_cache(int $uid, int $type = 1): void
    {
        \youvision\auth\Auth::clearCache($uid, $type);
    }
}