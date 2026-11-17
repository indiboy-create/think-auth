<?php

declare(strict_types=1);

/**
 * think-auth 自包含验证脚本。
 *
 * 检查 composer 声明、配置、目录结构、服务注册与核心逻辑，
 * 并通过桩类反射测试条件解析、类型安全比较与中间件白名单。
 * 无需启动 ThinkPHP 应用：php tests/verify.php
 */

$packageRoot = dirname(__DIR__);
$failures    = [];
$checks      = 0;

$check = static function (bool $ok, string $message) use (&$failures, &$checks): void {
    $checks++;

    if (!$ok) {
        $failures[] = $message;
    }
};

// ---------------------------------------------------------------------------
// composer.json
// ---------------------------------------------------------------------------
$composer = json_decode((string) file_get_contents($packageRoot . '/composer.json'), true);

$check(is_array($composer), 'composer.json 应为有效 JSON');
$check(($composer['name'] ?? '') === 'youvision/think-auth', '包名应为 youvision/think-auth');
$check(($composer['type'] ?? '') === 'library', 'type 应为 library');
$check(($composer['require']['php'] ?? '') === '^8.0', '应要求 php ^8.0');
$check(($composer['require']['topthink/framework'] ?? '') === '^8.0', '应依赖 topthink/framework ^8.0');
$check(
    ($composer['autoload']['psr-4']['youvision\\auth\\'] ?? '') === 'src/',
    '应配置 youvision\\auth 的 PSR-4 自动加载'
);
$check(in_array('src/helper.php', (array) ($composer['autoload']['files'] ?? []), true), '应加载 src/helper.php');
$check(
    in_array('youvision\\auth\\Service', (array) ($composer['extra']['think']['services'] ?? []), true),
    '应注册 youvision\\auth\\Service'
);
$check(
    ($composer['extra']['think']['config']['auth'] ?? '') === 'src/config.php',
    '应声明 src/config.php'
);
$check(($composer['scripts']['test'] ?? '') === '@php tests/verify.php', '应提供 composer test 脚本');

// ---------------------------------------------------------------------------
// 文件完整性
// ---------------------------------------------------------------------------
$files = [
    'src/Auth.php',
    'src/Service.php',
    'src/config.php',
    'src/helper.php',
    'src/service/Auth.php',
    'src/middleware/AuthCheck.php',
    'src/model/Role.php',
    'src/model/RoleRule.php',
    'src/model/RoleUser.php',
    'src/model/Rule.php',
    'src/model/User.php',
    'src/command/Config.php',
    'src/command/Install.php',
    'src/database/auth.sql',
    'tests/verify.php',
    'README.md',
    'LICENSE',
];

foreach ($files as $file) {
    $check(is_file($packageRoot . '/' . $file), $file . ' 应存在');
}

// ---------------------------------------------------------------------------
// 默认配置
// ---------------------------------------------------------------------------
$config = require $packageRoot . '/src/config.php';

$check(is_array($config), 'config.php 应返回数组');
$check(($config['auth_on'] ?? null) === 1, '默认 auth_on 应为 1');
$check(($config['auth_type'] ?? null) === 1, '默认 auth_type 应为 1');
$check(($config['auth_user'] ?? '') === 'member', '默认 auth_user 应为 member');
$check(is_array($config['middleware'] ?? null), '应包含 middleware 配置');
$check(($config['middleware']['user_id'] ?? '') === 'uid', '中间件默认用户 ID 来源应为 uid');

// ---------------------------------------------------------------------------
// 源码片段检查
// ---------------------------------------------------------------------------
$serviceSource = (string) file_get_contents($packageRoot . '/src/Service.php');
foreach ([
    'array_replace_recursive',
    "bind(AuthCheck::class",
    'method_exists($middleware',
    "'auth' => AuthCheck::class",
] as $snippet) {
    $check(str_contains($serviceSource, $snippet), 'Service 应包含：' . $snippet);
}

$managerSource = (string) file_get_contents($packageRoot . '/src/service/Auth.php');
foreach ([
    "request->get()",
    "'r.status', 1",
    'getAuthIndex',
    'authIndexCache',
    'roleCache',
    'getRoleRecords',
    'isEqual',
    'function clearCache',
    'splitCondition',
    'parse_str',
] as $snippet) {
    $check(str_contains($managerSource, $snippet), 'Auth 核心应包含：' . $snippet);
}
$check(!str_contains($managerSource, 'eval('), '条件解析不应使用 eval');
$check(!str_contains($managerSource, 'request->param()'), 'URL 参数校验不应使用含 POST 的 param()');

$mwSource = (string) file_get_contents($packageRoot . '/src/middleware/AuthCheck.php');
$check(str_contains($mwSource, "if (\$allow === '') {"), '中间件应跳过空串白名单项');

// ---------------------------------------------------------------------------
// SQL
// ---------------------------------------------------------------------------
$sql = (string) file_get_contents($packageRoot . '/src/database/auth.sql');
foreach (['auth_rule', 'auth_role', 'auth_role_user', 'auth_role_rule'] as $table) {
    $check(str_contains($sql, '__PREFIX__' . $table), 'SQL 应创建表：' . $table);
}
foreach ([
    'fk_auth_role_user_role',
    'fk_auth_role_rule_role',
    'fk_auth_role_rule_rule',
] as $fk) {
    $check(str_contains($sql, $fk), 'SQL 应定义外键：' . $fk);
}
$check(substr_count($sql, 'FOREIGN KEY') >= 3, 'SQL 应至少包含 3 个外键约束');
$check(str_contains($sql, 'ON DELETE CASCADE'), '外键应配置 ON DELETE CASCADE');

$installSource = (string) file_get_contents($packageRoot . '/src/command/Install.php');
$check(str_contains($installSource, 'ensureForeignKeys'), 'Install 应包含外键补充逻辑');
$check(str_contains($installSource, 'information_schema.KEY_COLUMN_USAGE'), 'Install 应检测已存在的外键');

// ---------------------------------------------------------------------------
// 运行时逻辑测试（桩类 + 反射，不依赖 vendor）
// ---------------------------------------------------------------------------
require $packageRoot . '/src/service/Auth.php';
require $packageRoot . '/src/middleware/AuthCheck.php';

use youvision\auth\middleware\AuthCheck;
use youvision\auth\service\Auth;

$ref  = new ReflectionClass(Auth::class);
$auth = $ref->newInstanceWithoutConstructor();

$cmp  = $ref->getMethod('compare');
$cond = $ref->getMethod('evaluateCondition');

// 类型安全比较
$check($cmp->invoke($auth, '0', '=', false) === false, "'0' 不应等于 false");
$check($cmp->invoke($auth, null, '=', '') === false, 'null 不应等于空字符串');
$check($cmp->invoke($auth, '1', '=', 1) === true, '数字字符串应等于整数');
$check($cmp->invoke($auth, '150', '>', 100) === true, '数值大小比较');

// 条件解析
$check($cond->invoke($auth, "{nickname} = 'Tom and Jerry' or {score}>10", ['nickname' => 'Tom and Jerry', 'score' => 1]) === true, '引号内 and 不被误拆');
$check($cond->invoke($auth, '{score}>=100 and {score}<200', ['score' => 150]) === true, 'and 区间条件');
$check($cond->invoke($auth, '{score}>10 or {score}<0', ['score' => 5]) === false, 'or 两支都不成立');

// 中间件白名单（用桩 Auth，反射调用 isAllowed）
$stubAuth = new class extends Auth {
    public function __construct()
    {
    }
};
$mwMethod = new ReflectionMethod(AuthCheck::class, 'isAllowed');
$mwMethod->setAccessible(true);

$mw = new AuthCheck($stubAuth, ['allow' => ['', 'user/login']]);
$check($mwMethod->invoke($mw, 'user/login') === true, '白名单空串不短路');
$check($mwMethod->invoke($mw, 'user/index') === false, '白名单不误匹配');
$mw = new AuthCheck($stubAuth, ['allow' => ['admin/*']]);
$check($mwMethod->invoke($mw, 'admin/user/index') === true, '白名单前缀通配');

// ---------------------------------------------------------------------------
// 输出
// ---------------------------------------------------------------------------
if ($failures !== []) {
    fwrite(STDERR, "验证失败（{$checks} 项检查，失败 " . count($failures) . " 项）：\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, '  - ' . $failure . "\n");
    }

    exit(1);
}

echo "验证通过：{$checks} 项检查全部成功。\n";
