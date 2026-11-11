<?php

declare(strict_types=1);

namespace youvision\auth\model;

use think\Model;
use think\model\relation\HasMany;

/**
 * 权限角色
 */
class Role extends Model
{
    protected $name = 'auth_role';

    protected $pk = 'id';

    public function getName(): string
    {
        return 'auth_role';
    }

    public function getPk(): string
    {
        return 'id';
    }

    /**
     * 删除角色时同步清理角色与规则、用户的关系数据
     */
    public static function onAfterDelete($role): void
    {
        RoleRule::where('role_id', $role->id)->delete();
        RoleUser::where('role_id', $role->id)->delete();
    }

    /**
     * 标准化状态值
     */
    protected function setStatusAttr($value): int
    {
        return in_array($value, ['on', 'true', '1', 1, true], true) ? 1 : 0;
    }

    /**
     * 角色下的用户数量
     */
    protected function getUserNumAttr(): int
    {
        return (int) RoleUser::where('role_id', $this->getData('id'))->count();
    }

    /**
     * 角色与规则的关联
     */
    public function rules(): HasMany
    {
        return $this->hasMany(RoleRule::class, 'role_id', 'id');
    }
}