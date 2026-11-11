<?php

declare(strict_types=1);

namespace youvision\auth;

use think\App;
use think\Service as BaseService;
use youvision\auth\command\Config;
use youvision\auth\command\Install;
use youvision\auth\service\Auth;

/**
 * 认证服务注册入口
 */
class Service extends BaseService
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * 注册配置与认证服务实例
     */
    public function register(): void
    {
        $default = require __DIR__ . '/config.php';
        $config  = (array) $this->app->config->get('auth', []);

        $this->app->config->set(array_merge($default, $config), 'auth');
        $this->app->instance(Auth::class, new Auth($this->app));
    }

    /**
     * 注册命令行指令
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                'auth:config'  => Config::class,
                'auth:install' => Install::class,
            ]);
        }
    }
}