<?php

declare(strict_types=1);

return [
    // 权限认证总开关：1 开启，0 关闭。关闭后 check() 一律返回 true
    'auth_on'   => 1,

    // 认证方式：1 实时认证，每次认证都查询数据库，权限变更即时生效；
    // 2 登录认证，认证结果在首次查询后写入 Session，需重新登录或清理 Session 才会刷新
    'auth_type' => 1,

    // 用户表名（不含数据库前缀），用于读取用户字段并解析规则 condition 中的 {字段名}
    // 例如用户表为 think_member 时，此处填写 member
    'auth_user' => 'member',

    // 认证中间件配置
    'middleware' => [
        // 无需认证的规则白名单，支持完整匹配与前缀通配，例如 user/login、user/*
        'allow' => [],

        // 用户 ID 来源：字符串为 Session 键名；闭包为 fn(Request $request): int
        // 不从请求参数读取用户 ID，避免客户端伪造
        'user_id' => 'uid',

        // 规则名生成闭包 fn(Request $request): string，留空使用 {控制器}/{方法}
        'rule' => null,

        // 未授权响应闭包 fn(Request $request): Response，留空返回 403 文本
        'deny' => null,
    ],
];