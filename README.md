# think-auth

ThinkPHP 8 权限认证扩展，基于规则（Rule）、角色（Role）、用户组关系（RoleUser、RoleRule）实现 RBAC 权限控制。支持按钮级、接口级、控制器/方法级认证，并可按用户字段设置条件规则。

## 环境要求

- PHP >= 8.0
- ThinkPHP >= 8.0

## 安装

```bash
composer require youvision/think-auth
```

安装完成后，服务与配置文件会自动注册。如果配置未生成或服务未生效，可执行：

```bash
php think service:discover
php think vendor:publish
```

## 配置

配置文件位于 `config/auth.php`，也可以执行以下命令生成：

```bash
php think auth:config
```

如果文件已存在需要覆盖，追加 `--force`：

```bash
php think auth:config --force
```

配置项说明：

| 配置项 | 默认值 | 说明 |
| --- | --- | --- |
| `auth_on` | `1` | 权限认证总开关。`1` 开启，`0` 关闭；关闭后 `check()` 一律返回 `true` |
| `auth_type` | `1` | 认证方式。`1` 实时认证，每次认证都查询数据库；`2` 登录认证，规则列表在首次查询后缓存到 Session |
| `auth_user` | `member` | 用户表名（不含数据库前缀），用于读取用户字段并解析规则中的 `{字段}` 条件 |

> 使用 `auth_type = 2` 时，需要在应用中启用 Session 中间件。权限变更后需重新登录或清理 Session 才会生效。

## 初始化数据表

执行安装命令，自动创建规则表、角色表及关系表，表名会自动拼接数据库配置中的前缀：

```bash
php think auth:install
```

创建的数据表：

| 表名 | 说明 |
| --- | --- |
| `auth_rule` | 权限规则表，`name` 为规则唯一标识，`status` 为 `1` 时有效，`condition` 为条件表达式 |
| `auth_role` | 权限角色表 |
| `auth_role_user` | 用户与角色关系表 |
| `auth_role_rule` | 角色与规则关系表 |

## 快速开始

### 方法签名

```php
// 检查权限：$name 支持逗号分隔字符串或数组；$type 为认证类型；
// $mode 为 url 时同时校验规则中的请求参数；$relation 支持 or / and
Auth::check(string|array $name, int $uid, int $type = 1, string $mode = 'url', string $relation = 'or'): bool;

// 获取用户满足条件的全部规则
Auth::rules(int $uid, int $type = 1): array;

// 获取用户所属角色；传入 $field 时仅返回该字段
Auth::roles(int $uid, string $field = '');
```

### 权限判断

```php
use youvision\auth\Auth;

if (Auth::check('admin/user/index', 1)) {
    // 拥有该规则权限
} else {
    // 无权限
}
```

也可以通过全局函数判断：

```php
if (auth_check('admin/user/index', 1)) {
    // 拥有该规则权限
}
```

### 多规则认证

多个规则默认按 `or` 关系认证，满足任意一个即通过：

```php
Auth::check('user/add,user/edit', 1);
```

将第五个参数 `$relation` 指定为 `and` 时，需要同时满足所有规则：

```php
Auth::check('user/add,user/edit', 1, 1, 'url', 'and');
```

规则传入数组同样有效：

```php
Auth::check(['user/add', 'user/edit'], 1);
```

### 控制器/方法级认证

将规则名称定义为 `控制器/方法` 的形式，例如 `admin/user/index`，然后在中间件或控制器基类中统一认证。

中间件示例：

```php
<?php

declare(strict_types=1);

namespace app\middleware;

use Closure;
use think\Request;
use think\Response;
use youvision\auth\Auth;

class AuthCheck
{
    // 无需认证的规则
    protected array $allow = ['user/login'];

    public function handle(Request $request, Closure $next): Response
    {
        $rule = strtolower($request->controller() . '/' . $request->action());

        if (!in_array($rule, $this->allow, true) && !Auth::check($rule, (int) $request->user_id)) {
            return response('403 未授权访问', 403);
        }

        return $next($request);
    }
}
```

> `$request->user_id` 需替换为应用实际的登录用户获取方式。

### 获取用户权限与角色

```php
use youvision\auth\Auth;

// 用户拥有的全部规则
$rules = Auth::rules(1);

// 用户所属的角色数据
$roles = Auth::roles(1);

// 用户所属的角色 ID
$roleIds = Auth::roles(1, 'role_id');
```

对应的全局函数：

```php
get_auth_rules(1);      // 全部规则
get_auth_roles(1);      // 全部角色
get_auth_role_ids(1);   // 全部角色 ID
```

## 权限模型

权限认证按规则进行。先定义规则，再将规则分配给角色，最后将用户加入角色：

1. 在 `auth_rule` 表中添加规则，例如 `user/edit`。
2. 在 `auth_role` 表中创建角色，例如「内容管理员」。
3. 在 `auth_role_rule` 表中关联角色与规则。
4. 在 `auth_role_user` 表中关联用户与角色。

一个用户可以属于多个角色，只要其中任意一个角色拥有目标规则，认证即通过。

## 条件规则

规则的 `condition` 字段可根据用户表字段设置附加条件。字段使用花括号包裹，例如用户表包含 `score` 字段：

| name | condition |
| --- | --- |
| `grade1` | `{score}<100` |
| `grade2` | `{score}>=100 and {score}<200` |
| `grade3` | `{score}>=200` |

支持的语法：

- 比较运算符：`>`、`>=`、`<`、`<=`、`=`、`==`、`!=`、`<>`
- 多个条件使用 `and`、`or` 组合，`and` 优先级高于 `or`
- 值支持数字、单引号或双引号字符串、`true`、`false`、`null`
- 仅写 `{字段}` 表示该字段有值即通过

> 条件解析不执行 PHP 代码，无法识别的表达式直接判定为不通过。不支持括号、函数调用和四则运算。

## URL 参数认证

当规则名称中带有查询参数时，认证还会校验当前请求参数，例如规则：

```text
user/index?type=admin
```

只有当前请求同时包含 `type=admin` 时，该规则才会被认为通过。

## 命令行

| 命令 | 说明 |
| --- | --- |
| `php think auth:config` | 生成 `config/auth.php` 配置文件 |
| `php think auth:config --force` | 覆盖已存在的配置文件 |
| `php think auth:install` | 创建认证数据表，已存在的表不会重复创建 |

## 目录结构

```text
src/
├── Auth.php             静态入口
├── Service.php          服务注册
├── config.php           默认配置
├── helper.php           全局函数
├── command/
│   ├── Config.php       发布配置命令
│   └── Install.php      创建数据表命令
├── database/
│   └── auth.sql         建表语句
├── model/
│   ├── Role.php
│   ├── RoleRule.php
│   ├── RoleUser.php
│   ├── Rule.php
│   └── User.php
└── service/
    └── Auth.php         认证核心逻辑
```

## 安全建议

- 规则名称应来自后端定义或固定白名单，不要直接使用未经校验的用户输入。
- 用户 ID 必须来自可信的登录态，不要使用请求参数中的用户 ID。
- 生产环境建议使用 `auth_type = 1`，权限变更可即时生效。