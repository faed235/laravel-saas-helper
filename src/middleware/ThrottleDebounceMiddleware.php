<?php

namespace Faed\LaravelSaasHelper\middleware;
use Closure;
use Faed\LaravelSaasHelper\exception\CustomException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ThrottleDebounceMiddleware
{
    /**
     * 防抖时间窗口（秒）
     */
    protected $debounceTime = 1; // X秒内只能请求一次

    public function handle(Request $request, Closure $next)
    {
        try {
            $this->antiRepeatTime(); // 使用当前用户 ID 防抖
            // 或 $this->antiRepeatTime('custom_unique_id', 5); // 自定义唯一 ID，防抖 5 秒
        } catch (CustomException $e) {
            return response()->json(['code' => 429, 'message' => $e->getMessage()]);
        }

        return $next($request);
    }

    function antiRepeatTime($unique_id = false, $seconds = 1): bool
    {
        // 如果没有传入 unique_id 且用户未登录，直接允许请求（或抛出异常）
        if (!$unique_id && !auth('api')->id()) {
            // 开发环境可以抛出异常，生产环境返回 true 或记录日志
            // throw new CustomException('未提供唯一标识且用户未登录');
            return true;
        }

        try {
            // 获取路由信息
            $action = request()->route()->getAction('uses');
            list($class, $method) = explode('@', $action);

            // 生成模块和控制器名称
            $modules = str_replace('\\', '.', str_replace('App\\Http\\Controllers\\', '', trim(implode('\\', array_slice(explode('\\', $class), 0, -1)), '\\')));
            $controller = str_replace('Controller', '', substr(strrchr($class, '\\'), 1));

            // 生成请求唯一标识（基于 URI、方法和参数）
            $parameter = request()->input();
            $uri = request()->getRequestUri();

            $filteredParams = array_except($parameter, ['ab_ac', 'group_uniqid']); // 过滤无关参数

            $md5 = md5($uri . '-' . request()->method() . '-' . json_encode($filteredParams));

            // 生成 Redis 键
            $userId = $unique_id ?: auth('api')->id();
            $key = sprintf(
                '%s-antiRepeatTime-%s.%s.%s.%s.%s',
                $_SERVER['HTTP_HOST'],
                $modules,
                $controller,
                $method,
                $md5,
                $userId
            );

            // 检查 Redis 锁
            if (Redis::get($key) == 1) {
                throw new CustomException('请求过于频繁，请稍后再试,1秒内请不要重复调用');
            }

            // 设置 Redis 锁（过期时间 $seconds 秒）
            Redis::setex($key, $seconds, 1);
            return true;

        } catch (\Exception $e) {
            // 记录日志或返回友好响应
            throw new CustomException($e->getMessage());
        }
    }
}