<?php

declare(strict_types=1);

namespace youvision\auth\model;

use think\Model;

/**
 * 权限规则
 */
class Rule extends Model
{
    protected $name = 'auth_rule';

    protected $pk = 'id';

    /**
     * 删除规则时同步清理角色与规则的关系数据
     */
    public static function onAfterDelete($rule): void
    {
        RoleRule::where('rule_id', $rule->id)->delete();
    }

    public function getName(): string
    {
        return 'auth_rule';
    }

    public function getPk(): string
    {
        return 'id';
    }
}