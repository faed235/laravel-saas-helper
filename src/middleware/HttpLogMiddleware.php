<?php

namespace Faed\LaravelSaasHelper\middleware;

use Faed\LaravelSaasHelper\log\AppendRequestIdProcessor;
use Faed\LaravelSaasHelper\notice\Wechat;
use Illuminate\Http\Request;
use Closure;
use Illuminate\Support\Facades\Log;

class HttpLogMiddleware
{
    protected const SLOW_REQUEST_THRESHOLD = 3.0; // 慢请求阈值（秒）
    public function handle(Request $request, Closure $next){
        $startTime = microtime(true);
        $userId = auth()->id();
        $this->logRequest($request, $userId);

        $response = $next($request);

        $executionTime = $this->calculateExecutionTime($startTime);
        $this->logResponse($request, $response, $executionTime, $userId);

        $this->handleSlowRequest($request, $executionTime, $userId);

        return $response;
    }


    protected function logRequest(Request $request, $userId): void
    {
        Log::channel(config('laravel-saas-helper.log.http_channel'))->info('请求', [
            'host'=>$request->getSchemeAndHttpHost(),
            '请求url'=>$request->getRequestUri(),
            '路由'=>$request->route()->uri(),
            '方式'=>$request->method(),
            '体'=>$request->input(),
            '用户'=>$userId,
        ]);
    }

    protected function logResponse(Request $request, $response, float $executionTime, $userId): void
    {
        Log::channel(config('laravel-saas-helper.log.http_channel'))->info('响应', [
            '执行时间'=>$executionTime,
            '路由'=>$request->route()->uri(),
            '用户'=>$userId,
            '状态'=>$response->getStatusCode(),
        ]);
    }

    protected function calculateExecutionTime(float $startTime): float
    {
        return round(microtime(true) - $startTime, 2);
    }

    protected function handleSlowRequest(Request $request, float $executionTime, $userId): void
    {
        if ($executionTime > self::SLOW_REQUEST_THRESHOLD) {
            $this->sendSlowRequestAlert($request, $executionTime, $userId);
        }
    }

    protected function sendSlowRequestAlert(Request $request, float $executionTime, $userId): void
    {
        Wechat::sendText([
            'title' => '执行时间过长',
            'app_name' => env('APP_NAME'),
            'run_time' => sprintf('%.4fs', $executionTime),
            'host'=>$request->getSchemeAndHttpHost(),
            '请求url'=>$request->getRequestUri(),
            '路由'=>$request->route()->uri(),
            '方式'=>$request->method(),
            '体'=>$request->input(),
            '用户'=>$userId,
            'trace' => AppendRequestIdProcessor::getOrSet(),
        ]);
    }
}