-- 权限规则表
CREATE TABLE IF NOT EXISTS `__PREFIX__auth_rule` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '规则ID',
  `name` varchar(80) NOT NULL DEFAULT '' COMMENT '规则唯一标识',
  `title` varchar(80) NOT NULL DEFAULT '' COMMENT '规则名称',
  `type` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '规则类型',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态：1正常，0禁用',
  `condition` varchar(255) NOT NULL DEFAULT '' COMMENT '条件表达式',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限规则表';

-- 权限角色表
CREATE TABLE IF NOT EXISTS `__PREFIX__auth_role` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '角色ID',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '角色名称',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '状态：1正常，0禁用',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限角色表';

-- 用户与角色关系表
CREATE TABLE IF NOT EXISTS `__PREFIX__auth_role_user` (
  `user_id` bigint unsigned NOT NULL COMMENT '用户ID',
  `role_id` int unsigned NOT NULL COMMENT '角色ID',
  UNIQUE KEY `user_role` (`user_id`, `role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户与角色关系表';

-- 角色与规则关系表
CREATE TABLE IF NOT EXISTS `__PREFIX__auth_role_rule` (
  `rule_id` int unsigned NOT NULL COMMENT '规则ID',
  `role_id` int unsigned NOT NULL COMMENT '角色ID',
  PRIMARY KEY (`rule_id`, `role_id`),
  KEY `role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色与规则关系表';