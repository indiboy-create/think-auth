<?php

declare(strict_types=1);

namespace youvision\auth\service;

use think\App;
use think\facade\Session;
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

        $authList = $this->getAuthList($uid, $type);
        $names    = $this->normalizeNames($name);

        if ($names === []) {
            return false;
        }

        $request = $mode === 'url' ? $this->lowercase((array) $this->app->request->param()) : [];
        $passed  = [];

        foreach ($authList as $auth) {
            $query = (string) preg_replace('/^.+\?/U', '', $auth);

            if ($mode === 'url' && $query !== $auth) {
                parse_str($query, $param);
                $intersect = array_intersect_assoc($request, $param);
                $node      = (string) preg_replace('/\?.*$/U', '', $auth);

                if (in_array($node, $names, true) && $intersect == $param) {
                    $passed[] = $node;
                }
            } elseif (in_array($auth, $names, true)) {
                $passed[] = $auth;
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
        $query = RoleUser::where('user_id', $uid);

        if ($field !== '') {
            return $query->column($field);
        }

        return $query->select();
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

        $roleIds = (array) RoleUser::where('user_id', $uid)->column('role_id');
        if ($roleIds === []) {
            return $this->authListCache[$cacheKey] = [];
        }

        $userModel = (new User())->useTable((string) $this->config['auth_user']);
        $user      = $userModel->where($userModel->getPk(), $uid)->find();
        $userData  = $user ? $user->toArray() : [];

        $authList = [];
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
            if ($condition === '' || $this->evaluateCondition($condition, $userData)) {
                $authList[] = $ruleName;
            }
        }

        $authList = array_values(array_unique($authList));
        $this->authListCache[$cacheKey] = $authList;

        if ((int) $this->config['auth_type'] === 2) {
            Session::set($sessionKey, $authList);
        }

        return $authList;
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
        $orGroups = preg_split('/\s+or\s+/i', $condition) ?: [];

        foreach ($orGroups as $group) {
            $andParts = preg_split('/\s+and\s+/i', trim($group)) ?: [];
            $matched  = $andParts !== [];

            foreach ($andParts as $part) {
                if (!$this->evaluateExpression(trim($part), $user)) {
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
     * 解析单个比较表达式
     */
    protected function evaluateExpression(string $expression, array $user): bool
    {
        if (preg_match('/^\{(\w+)\}$/', $expression, $matches)) {
            return !empty($user[$matches[1]] ?? null);
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
            return $left == $right;
        }

        if (in_array($operator, ['!=', '<>'], true)) {
            return $left != $right;
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