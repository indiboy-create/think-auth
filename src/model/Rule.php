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

    public function getName(): string
    {
        return 'auth_rule';
    }

    public function getPk(): string
    {
        return 'id';
    }
}