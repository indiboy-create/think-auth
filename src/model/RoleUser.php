<?php

declare(strict_types=1);

namespace youvision\auth\model;

use think\Model;
use think\model\relation\HasMany;
use think\model\relation\HasOne;

/**
 * 用户与角色的关系
 */
class RoleUser extends Model
{
    protected $name = 'auth_role_user';

    protected $pk = 'role_id';

    public function getName(): string
    {
        return 'auth_role_user';
    }

    public function getPk(): string
    {
        return 'role_id';
    }

    /**
     * 角色对应的规则
     */
    public function rules(): HasMany
    {
        return $this->hasMany(RoleRule::class, 'role_id', 'role_id');
    }

    /**
     * 对应的角色
     */
    public function role(): HasOne
    {
        return $this->hasOne(Role::class, 'id', 'role_id');
    }
}