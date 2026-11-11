<?php

declare(strict_types=1);

namespace youvision\auth\model;

use think\Model;
use think\model\relation\HasOne;

/**
 * 角色与规则的关系
 */
class RoleRule extends Model
{
    protected $name = 'auth_role_rule';

    protected $pk = 'role_id';

    public function getName(): string
    {
        return 'auth_role_rule';
    }

    public function getPk(): string
    {
        return 'role_id';
    }

    /**
     * 对应的规则
     */
    public function rule(): HasOne
    {
        return $this->hasOne(Rule::class, 'id', 'rule_id');
    }

    /**
     * 兼容旧关联名称
     */
    public function rules(): HasOne
    {
        return $this->rule();
    }
}