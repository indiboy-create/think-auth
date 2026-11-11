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

        $output->writeln('<info>Auth tables installed, prefix: ' . ($prefix === '' ? '(none)' : $prefix) . '</info>');
        return 0;
    }
}