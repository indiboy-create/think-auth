<?php

declare(strict_types=1);

namespace youvision\auth\model;

use think\Model;

/**
 * 用户模型
 *
 * 表名由 auth.auth_user 配置指定，不包含数据库前缀
 */
class User extends Model
{
    protected $name = 'member';

    protected $pk = 'id';

    /**
     * 设置用户表名（不含数据库前缀）
     */
    public function useTable(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPk(): string
    {
        return 'id';
    }
}