<?php

declare(strict_types=1);

namespace youvision\auth\middleware;

use Closure;
use think\facade\Session;
use think\Request;
use think\Response;
use youvision\auth\service\Auth;

/**
 * 认证中间件。
 *
 * 默认规则名为 {控制器}/{方法}，用户 ID 默认从 Session 的 uid 键读取。
 * 白名单支持完整匹配与前缀通配，例如 user/login、user/*。
 */
class AuthCheck
{
    protected Auth $auth;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param array<string, mixed> $config auth.middleware 配置
     */
    public function __construct(Auth $auth, array $config = [])
    {
        $this->auth   = $auth;
        $this->config = $config;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $rule = $this->resolveRule($request);

        if ($this->isAllowed($rule)) {
            return $next($request);
        }

        $uid = $this->resolveUserId($request);

        if ($uid > 0 && $this->auth->check($rule, $uid)) {
            return $next($request);
        }

        return $this->deny($request);
    }

    /**
     * 生成待校验的规则名。
     */
    protected function resolveRule(Request $request): string
    {
        $resolver = $this->config['rule'] ?? null;

        if ($resolver instanceof Closure) {
            return strtolower(trim((string) $resolver($request)));
        }

        return strtolower(trim($request->controller() . '/' . $request->action()));
    }

    /**
     * 白名单判断，支持 * 与前缀通配。
     */
    protected function isAllowed(string $rule): bool
    {
        foreach ((array) ($this->config['allow'] ?? []) as $allow) {
            $allow = strtolower(trim((string) $allow));

            if ($allow === '') {
                continue;
            }

            if ($allow === '*') {
                return true;
            }

            if ($allow === $rule) {
                return true;
            }

            if (str_ends_with($allow, '*') && str_starts_with($rule, rtrim($allow, '*'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取用户 ID。
     *
     * user_id 为闭包时调用闭包；为字符串时作为 Session 键名。
     * 不从请求参数读取，避免客户端伪造用户 ID。
     */
    protected function resolveUserId(Request $request): int
    {
        $source = $this->config['user_id'] ?? 'uid';

        if ($source instanceof Closure) {
            return (int) $source($request);
        }

        if (is_string($source) && $source !== '') {
            return (int) Session::get($source, 0);
        }

        return 0;
    }

    /**
     * 未授权响应。
     */
    protected function deny(Request $request): Response
    {
        $deny = $this->config['deny'] ?? null;

        if ($deny instanceof Closure) {
            return $deny($request);
        }

        return response('403 未授权访问', 403);
    }
}
