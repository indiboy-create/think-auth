<?php

declare(strict_types=1);

namespace youvision\auth\service;

use think\App;
use think\facade\Session;
use youvision\auth\model\Role;
use youvision\auth\model\RoleRule;
use youvision\auth\model\RoleUser;
use youvision\auth\model\User;

/**
 * 权限认证核心服务
 */
class Auth
{
    protected App $app;

    /**
     * 认证配置
     *
     * @var array<string, mixed>
     */
    protected array $config = [];

    /**
     * 当前请求内的规则缓存
     *
     * @var array<string, array>
     */
    protected array $authListCache = [];

    /**
     * 当前请求内的规则索引缓存，键为 uid:type
     *
     * @var array<string, array<string, array<int, array<string, mixed>>>>
     */
    protected array $authIndexCache = [];

    /**
     * 当前请求内的角色缓存，键为 uid
     *
     * @var array<int, array{ids: array<int, int>, identities: array<int, int|string>}>
     */
    protected array $roleCache = [];

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->config = array_merge([
            'auth_on'   => 1,
            'auth_type' => 1,
            'auth_user' => 'member',
        ], (array) $app->config->get('auth', []));
    }

    /**
     * 检查用户是否拥有指定规则
     *
     * @param string|array $name     规则名称，支持逗号分隔字符串或数组
     * @param int          $uid      用户 ID
     * @param int          $type     认证类型
     * @param string       $mode     认证模式，url 表示同时校验规则中的请求参数
     * @param string       $relation 多规则关系：or 满足其一即通过，and 需全部满足
     */
    public function check(string|array $name, int $uid, int $type = 1, string $mode = 'url', string $relation = 'or'): bool
    {
        if (!$this->config['auth_on']) {
            return true;
        }

        $names = $this->normalizeNames($name);
        if ($names === []) {
            return false;
        }

        $authList = $this->getAuthList($uid, $type);
        if ($authList === []) {
            return false;
        }

        $request   = $mode === 'url' ? $this->lowercase((array) $this->app->request->get()) : [];
        $authIndex = $this->getAuthIndex($uid, $type, $authList);
        $passed    = [];

        foreach ($names as $candidate) {
            [$nameNode, $nameParam] = $this->splitRule($candidate);

            if (!isset($authIndex[$nameNode])) {
                continue;
            }

            foreach ($authIndex[$nameNode] as $ruleParam) {
                if ($nameParam !== [] && $nameParam !== $ruleParam) {
                    continue;
                }

                if ($ruleParam !== [] && $mode === 'url' && !$this->matchParams($request, $ruleParam)) {
                    continue;
                }

                $passed[] = $candidate;
                break;
            }
        }

        if ($relation === 'or') {
            return $passed !== [];
        }

        return $relation === 'and' && array_diff($names, $passed) === [];
    }

    /**
     * 获取用户拥有的全部规则名称
     */
    public function rules(int $uid, int $type = 1): array
    {
        return $this->getAuthList($uid, $type);
    }

    /**
     * 获取用户所属的角色
     *
     * @param int    $uid   用户 ID
     * @param string $field 指定字段时仅返回该字段值
     * @return mixed
     */
    public function roles(int $uid, string $field = '')
    {
        $roles = $this->getRoleRecords($uid);

        if ($field !== '') {
            return array_values(array_map(
                static fn ($role) => $role[$field] ?? null,
                $roles
            ));
        }

        return $roles;
    }

    /**
     * 判断是否拥有指定角色（ID 或角色名）
     *
     * @param int|string $role 角色 ID 或角色名称
     */
    public function hasRole(int $uid, int|string $role): bool
    {
        return $this->hasAnyRole($uid, [$role]);
    }

    /**
     * 判断是否拥有角色列表中的任意一个
     *
     * @param array<int, int|string> $roles
     */
    public function hasAnyRole(int $uid, array $roles): bool
    {
        $owned = $this->getRoleIdentities($uid);

        foreach ($roles as $role) {
            if (in_array($role, $owned, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断是否同时拥有角色列表中的全部角色
     *
     * @param array<int, int|string> $roles
     */
    public function hasAllRoles(int $uid, array $roles): bool
    {
        if ($roles === []) {
            return false;
        }

        $owned = $this->getRoleIdentities($uid);

        foreach ($roles as $role) {
            if (!in_array($role, $owned, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 用户角色的 ID 与名称集合，用于角色判断
     *
     * @return array<int, int|string>
     */
    protected function getRoleIdentities(int $uid): array
    {
        if (isset($this->roleCache[$uid]['identities'])) {
            return $this->roleCache[$uid]['identities'];
        }

        $identities = [];

        foreach ($this->getRoleRecords($uid) as $role) {
            $identities[] = (int) $role['id'];
            $identities[] = (string) $role['title'];
        }

        $this->roleCache[$uid]['identities'] = $identities;

        return $identities;
    }

    /**
     * 获取用户的有效规则列表
     *
     * @return array<int, string>
     */
    protected function getAuthList(int $uid, int $type): array
    {
        $cacheKey = $uid . ':' . implode(',', (array) $type);

        if (array_key_exists($cacheKey, $this->authListCache)) {
            return $this->authListCache[$cacheKey];
        }

        $sessionKey = '_auth_list_' . $cacheKey;
        if ((int) $this->config['auth_type'] === 2 && Session::has($sessionKey)) {
            return $this->authListCache[$cacheKey] = (array) Session::get($sessionKey);
        }

        $roleIds = $this->getRoleIds($uid);
        if ($roleIds === []) {
            return $this->authListCache[$cacheKey] = [];
        }

        $authList = [];
        $userData = null;
        $roleRules = RoleRule::whereIn('role_id', $roleIds)->with(['rule'])->select();

        foreach ($roleRules as $roleRule) {
            $rule = $roleRule->rule;

            if (!$rule || (int) $rule->status !== 1) {
                continue;
            }

            $ruleName = strtolower(trim((string) $rule->name));
            if ($ruleName === '') {
                continue;
            }

            $condition = trim((string) $rule->condition);
            if ($condition !== '') {
                if ($userData === null) {
                    $userData = $this->getUserData($uid);
                }

                if (!$this->evaluateCondition($condition, $userData)) {
                    continue;
                }
            }

            $authList[] = $ruleName;
        }

        $authList = array_values(array_unique($authList));
        $this->authListCache[$cacheKey] = $authList;

        if ((int) $this->config['auth_type'] === 2) {
            Session::set($sessionKey, $authList);
        }

        return $authList;
    }

    /**
     * 获取用户所属启用角色的 ID
     *
     * @return array<int, int>
     */
    protected function getRoleIds(int $uid): array
    {
        if (isset($this->roleCache[$uid]['ids'])) {
            return $this->roleCache[$uid]['ids'];
        }

        $roleTable = (new Role())->getTable();
        $roleIds   = array_values(array_map('intval', (array) RoleUser::alias('ru')
            ->join($roleTable . ' r', 'r.id = ru.role_id')
            ->where('ru.user_id', $uid)
            ->where('r.status', 1)
            ->column('ru.role_id')));

        return $this->roleCache[$uid]['ids'] = $roleIds;
    }

    /**
     * 用户所属启用角色的记录（请求内缓存）
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getRoleRecords(int $uid): array
    {
        if (isset($this->roleCache[$uid]['records'])) {
            return $this->roleCache[$uid]['records'];
        }

        $records = [];

        foreach (Role::whereIn('id', $this->getRoleIds($uid))->select() as $role) {
            $records[] = $role->toArray();
        }

        return $this->roleCache[$uid]['records'] = $records;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getUserData(int $uid): array
    {
        $userModel = (new User())->useTable((string) $this->config['auth_user']);
        $user      = $userModel->where($userModel->getPk(), $uid)->find();

        return $user ? $user->toArray() : [];
    }

    /**
     * 获取规则索引（请求内缓存），避免同一请求多次 check 重复拆分规则。
     *
     * @param array<int, string> $authList
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function getAuthIndex(int $uid, int $type, array $authList): array
    {
        $cacheKey = $uid . ':' . implode(',', (array) $type);

        if (isset($this->authIndexCache[$cacheKey])) {
            return $this->authIndexCache[$cacheKey];
        }

        return $this->authIndexCache[$cacheKey] = $this->buildAuthIndex($authList);
    }

    /**
     * 以规则节点为键建立索引，值为该节点下所有可能的参数组合。
     *
     * @param array<int, string> $authList
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function buildAuthIndex(array $authList): array
    {
        $index = [];

        foreach ($authList as $auth) {
            [$node, $param] = $this->splitRule($auth);
            $index[$node][] = $param;
        }

        return $index;
    }

    /**
     * 拆分规则名为节点与查询参数
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function splitRule(string $rule): array
    {
        $pos = strpos($rule, '?');

        if ($pos === false) {
            return [$rule, []];
        }

        parse_str(substr($rule, $pos + 1), $param);

        return [substr($rule, 0, $pos), $this->lowercase($param)];
    }

    /**
     * 请求参数是否覆盖规则要求的全部参数
     */
    protected function matchParams(array $request, array $param): bool
    {
        foreach ($param as $key => $value) {
            if (!array_key_exists($key, $request) || (string) $request[$key] !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * 清理指定用户的 Session 规则缓存
     */
    public function clearCache(int $uid, int $type = 1): void
    {
        $cacheKey   = $uid . ':' . implode(',', (array) $type);
        $sessionKey = '_auth_list_' . $cacheKey;

        unset($this->authListCache[$cacheKey], $this->authIndexCache[$cacheKey], $this->roleCache[$uid]);

        if (Session::has($sessionKey)) {
            Session::delete($sessionKey);
        }
    }

    /**
     * 格式化规则名称
     *
     * @param string|array $name
     * @return array<int, string>
     */
    protected function normalizeNames(string|array $name): array
    {
        $names = is_string($name) ? explode(',', $name) : $name;
        $names = array_map(static fn ($item): string => strtolower(trim((string) $item)), $names);

        return array_values(array_filter($names, static fn (string $item): bool => $item !== ''));
    }

    /**
     * 递归转换请求参数为小写
     *
     * @param array $data
     * @return array
     */
    protected function lowercase(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $key = strtolower((string) $key);

            if (is_array($value)) {
                $result[$key] = $this->lowercase($value);
            } elseif (is_string($value)) {
                $result[$key] = strtolower($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * 解析规则条件，支持 and / or 组合的多段比较表达式
     *
     * 示例：{score}>100 and {score}<200
     */
    protected function evaluateCondition(string $condition, array $user): bool
    {
        $condition = trim($condition);

        if ($condition === '') {
            return false;
        }

        // 整体被一对括号包裹时，去掉外层括号后递归解析
        if ($this->isWrappedInParentheses($condition)) {
            return $this->evaluateCondition(substr($condition, 1, -1), $user);
        }

        foreach ($this->splitCondition($condition, 'or') as $group) {
            $group    = trim($group);
            $andParts = $this->splitCondition($group, 'and');
            $matched  = $andParts !== [];

            foreach ($andParts as $part) {
                $part = trim($part);

                // 单个 and 片段被括号包裹时递归解析
                if ($this->isWrappedInParentheses($part)) {
                    $ok = $this->evaluateCondition(substr($part, 1, -1), $user);
                } else {
                    $ok = $this->evaluateExpression($part, $user);
                }

                if (!$ok) {
                    $matched = false;
                    break;
                }
            }

            if ($matched) {
                return true;
            }
        }

        return false;
    }

    /**
     * 判断字符串是否整体被一对括号包裹。
     */
    protected function isWrappedInParentheses(string $expr): bool
    {
        $length = strlen($expr);

        if ($length < 2 || $expr[0] !== '(' || $expr[$length - 1] !== ')') {
            return false;
        }

        $depth = 0;

        for ($i = 0; $i < $length; $i++) {
            if ($expr[$i] === '(') {
                $depth++;
            } elseif ($expr[$i] === ')') {
                $depth--;

                if ($depth === 0 && $i !== $length - 1) {
                    return false;
                }
            }
        }

        return $depth === 0;
    }

    /**
     * 按逻辑运算符拆分条件，忽略单双引号及括号内的 and / or。
     *
     * @return array<int, string>
     */
    protected function splitCondition(string $condition, string $logic): array
    {
        $parts  = [];
        $buffer = '';
        $length = strlen($condition);
        $quote  = '';
        $depth  = 0;
        $size   = strlen($logic);

        for ($i = 0; $i < $length; $i++) {
            $char = $condition[$i];

            if ($quote !== '') {
                $buffer .= $char;

                if ($char === $quote && !$this->isEscaped($condition, $i)) {
                    $quote = '';
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote  = $char;
                $buffer .= $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
                $buffer .= $char;

                continue;
            }

            if ($char === ')') {
                $depth--;
                $buffer .= $char;

                continue;
            }

            if (
                $depth === 0
                && strcasecmp(substr($condition, $i, $size), $logic) === 0
                && $this->isConditionBoundary($condition, $i - 1)
                && $this->isConditionBoundary($condition, $i + $size)
            ) {
                $part = trim($buffer);
                if ($part !== '') {
                    $parts[] = $part;
                }

                $buffer = '';
                $i     += $size - 1;

                continue;
            }

            $buffer .= $char;
        }

        $part = trim($buffer);
        if ($part !== '') {
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * 判断字符串指定位置的字符是否被奇数个反斜杠转义。
     */
    protected function isEscaped(string $value, int $index): bool
    {
        $slashes = 0;

        for ($i = $index - 1; $i >= 0 && $value[$i] === '\\'; $i--) {
            $slashes++;
        }

        return $slashes % 2 === 1;
    }

    /**
     * 逻辑运算符两侧必须为字符串边界或空白字符。
     */
    protected function isConditionBoundary(string $condition, int $index): bool
    {
        return $index < 0 || $index >= strlen($condition) || ctype_space($condition[$index]);
    }

    /**
     * 解析单个比较表达式
     */
    protected function evaluateExpression(string $expression, array $user): bool
    {
        if (preg_match('/^\{(\w+)\}$/', $expression, $matches)) {
            $value = $user[$matches[1]] ?? null;

            return $value !== null && $value !== '';
        }

        if (!preg_match('/^\{(\w+)\}\s*(>=|<=|<>|!=|==|=|>|<)\s*(.+)$/', $expression, $matches)) {
            return false;
        }

        [, $field, $operator, $right] = $matches;

        if (!array_key_exists($field, $user)) {
            return false;
        }

        return $this->compare($user[$field], $operator, $this->parseValue($right));
    }

    /**
     * 比较字段值与规则值
     *
     * @param mixed  $left
     * @param string $operator
     * @param mixed  $right
     */
    protected function compare($left, string $operator, $right): bool
    {
        if (in_array($operator, ['=', '=='], true)) {
            return $this->isEqual($left, $right);
        }

        if (in_array($operator, ['!=', '<>'], true)) {
            return !$this->isEqual($left, $right);
        }

        // null 与布尔值不参与大小比较
        if ($left === null || $right === null || is_bool($left) || is_bool($right)) {
            return false;
        }

        if (is_numeric($left) && is_numeric($right)) {
            $left  = (float) $left;
            $right = (float) $right;
        } else {
            $left  = (string) $left;
            $right = (string) $right;
        }

        return match ($operator) {
            '>'     => $left > $right,
            '>='    => $left >= $right,
            '<'     => $left < $right,
            '<='    => $left <= $right,
            default => false,
        };
    }

    /**
     * 类型安全的相等判断，避免 PHP 松散比较的类型 juggling。
     *
     * - null 只与 null 相等
     * - 布尔值只与布尔值相等
     * - 数值与数值字符串按数值比较，兼顾数据库字段常为字符串的情况
     * - 其余情况按字符串严格比较
     *
     * @param mixed $left
     * @param mixed $right
     */
    protected function isEqual($left, $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        if (is_bool($left) || is_bool($right)) {
            return is_bool($left) && is_bool($right) && $left === $right;
        }

        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left === (float) $right;
        }

        return (string) $left === (string) $right;
    }

    /**
     * 转换规则中的比较值
     *
     * @return mixed
     */
    protected function parseValue(string $value)
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $first = $value[0];
        if (($first === "'" || $first === '"') && substr($value, -1) === $first) {
            return substr($value, 1, -1);
        }

        $lower = strtolower($value);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }
}