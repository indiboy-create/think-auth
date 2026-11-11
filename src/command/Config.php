<?php

declare(strict_types=1);

namespace youvision\auth\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 发布认证配置文件
 */
class Config extends Command
{
    protected function configure(): void
    {
        $this->setName('auth:config')
            ->addOption('force', 'f', Option::VALUE_NONE, 'Overwrite the existing config file')
            ->setDescription('Publish the auth config file');
    }

    protected function execute(Input $input, Output $output): int
    {
        $target = $this->app->getConfigPath() . 'auth.php';

        if (is_file($target) && !$input->getOption('force')) {
            $output->writeln('<info>Config file already exists: ' . $target . '</info>');
            return 0;
        }

        $content = file_get_contents(dirname(__DIR__) . '/config.php');

        if ($content === false || file_put_contents($target, $content) === false) {
            $output->writeln('<error>Unable to write config file: ' . $target . '</error>');
            return 1;
        }

        $output->writeln('<info>Config file created: ' . $target . '</info>');
        return 0;
    }
}