<?php

declare(strict_types=1);

namespace youvision\auth;

use think\App;
use think\Service as BaseService;
use youvision\auth\command\Config;
use youvision\auth\command\Install;
use youvision\auth\middleware\AuthCheck;
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

        $this->app->config->set(array_replace_recursive($default, $config), 'auth');
        $this->app->instance(Auth::class, new Auth($this->app));

        $this->app->bind(AuthCheck::class, function () {
            return new AuthCheck(
                $this->app->make(Auth::class),
                (array) $this->app->config->get('auth.middleware', [])
            );
        });
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

        // 注册中间件别名，路由中可直接用 'auth'
        // 宿主应用可能未注册 middleware 服务（精简场景或纯 CLI），做防御处理
        try {
            $middleware = $this->app->middleware ?? null;

            if (is_object($middleware) && method_exists($middleware, 'alias')) {
                $middleware->alias([
                    'auth' => AuthCheck::class,
                ]);
            }
        } catch (\Throwable $e) {
            // middleware 服务不可用时跳过别名注册，不影响认证核心功能
        }
    }
}