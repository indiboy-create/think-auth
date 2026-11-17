<?php

declare(strict_types=1);

namespace youvision\auth\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

/**
 * 初始化认证数据表
 */
class Install extends Command
{
    protected function configure(): void
    {
        $this->setName('auth:install')
            ->setDescription('Create the auth tables');
    }

    protected function execute(Input $input, Output $output): int
    {
        $file = dirname(__DIR__) . '/database/auth.sql';

        if (!is_file($file)) {
            $output->writeln('<error>SQL file not found: ' . $file . '</error>');
            return 1;
        }

        $connection = (string) $this->app->config->get('database.default', 'mysql');
        $prefix     = (string) $this->app->config->get('database.connections.' . $connection . '.prefix', '');
        $sql        = (string) file_get_contents($file);

        $lines = preg_split('/\r\n|\n|\r/', $sql) ?: [];
        $lines = array_filter($lines, static fn (string $line): bool => !str_starts_with(trim($line), '--'));

        $statements = array_filter(array_map('trim', explode(';', implode("\n", $lines))));

        foreach ($statements as $statement) {
            $this->app->db->execute(str_replace('__PREFIX__', $prefix, $statement));
        }

        // 已存在的表不会因 CREATE TABLE IF NOT EXISTS 补上外键，这里幂等补充
        $this->ensureForeignKeys($prefix, $output);

        $output->writeln('<info>Auth tables installed, prefix: ' . ($prefix === '' ? '(none)' : $prefix) . '</info>');
        return 0;
    }

    /**
     * 为可能已存在的表补充外键约束（幂等）。
     * 仅 MySQL 的 information_schema 可用时执行，其余数据库由建表语句保证。
     *
     * @param string $prefix
     * @return void
     */
    protected function ensureForeignKeys(string $prefix, Output $output): void
    {
        $keys = [
            [
                'table'      => $prefix . 'auth_role_user',
                'constraint' => $prefix . 'fk_auth_role_user_role',
                'sql'        => 'ALTER TABLE `__PREFIX__auth_role_user` ADD CONSTRAINT `__PREFIX__fk_auth_role_user_role` FOREIGN KEY (`role_id`) REFERENCES `__PREFIX__auth_role` (`id`) ON DELETE CASCADE',
            ],
            [
                'table'      => $prefix . 'auth_role_rule',
                'constraint' => $prefix . 'fk_auth_role_rule_role',
                'sql'        => 'ALTER TABLE `__PREFIX__auth_role_rule` ADD CONSTRAINT `__PREFIX__fk_auth_role_rule_role` FOREIGN KEY (`role_id`) REFERENCES `__PREFIX__auth_role` (`id`) ON DELETE CASCADE',
            ],
            [
                'table'      => $prefix . 'auth_role_rule',
                'constraint' => $prefix . 'fk_auth_role_rule_rule',
                'sql'        => 'ALTER TABLE `__PREFIX__auth_role_rule` ADD CONSTRAINT `__PREFIX__fk_auth_role_rule_rule` FOREIGN KEY (`rule_id`) REFERENCES `__PREFIX__auth_rule` (`id`) ON DELETE CASCADE',
            ],
        ];

        foreach ($keys as $key) {
            try {
                $exists = $this->app->db->query(
                    'SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? LIMIT 1',
                    [$key['table'], $key['constraint']]
                );

                if ($exists !== []) {
                    continue;
                }

                $this->app->db->execute(str_replace('__PREFIX__', $prefix, $key['sql']));
                $output->writeln('<info>Foreign key added: ' . $key['constraint'] . '</info>');
            } catch (\Throwable $e) {
                // 非 MySQL 或无权限访问 information_schema 时跳过，建表语句已尽力保证
                $output->writeln('<comment>Skip foreign key ' . $key['constraint'] . ': ' . $e->getMessage() . '</comment>');
            }
        }
    }
}