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
        try {
            $userId = auth()->payload()->get('sub');
        }catch (\Exception $exception){
            $userId = 0;
        };
        $additionalData = $this->getAdditionalRequestData($request); // 获取额外参数


        $this->logRequest($request, $userId,$additionalData);

        $response = $next($request);

        $executionTime = $this->calculateExecutionTime($startTime);
        $this->logResponse($request, $response, $executionTime, $userId,$additionalData);

        $this->handleSlowRequest($request, $executionTime, $userId);

        return $response;
    }


    protected function logRequest(Request $request, $userId,array $additionalData = []): void
    {
        Log::channel(config('laravel-saas-helper.log.http_channel'))->info('请求', array_merge($additionalData,[
            'uri'=>$request->getRequestUri(),
            'route'=>$request->route()->uri(),
            'method'=>$request->method(),
            'body'=>$request->input(),
            'user'=>$userId,
        ]));
    }

    protected function logResponse(Request $request, $response, float $executionTime, $userId,array $additionalData = []): void
    {
        Log::channel(config('laravel-saas-helper.log.http_channel'))->info('响应', array_merge($additionalData,[
            'time'=>$executionTime,
            'uri'=>$request->getRequestUri(),
            'route'=>$request->route()->uri(),
            'user'=>$userId,
            'code'=>$response->getStatusCode(),
        ]));
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

    protected function getAdditionalRequestData(Request $request): array
    {
        $data = [];
        foreach (config('laravel-saas-helper.log.extra_headers_to_log', []) as $value) {
            $data[$value] = $request->header($value);
        }
        return $data;
    }


}


