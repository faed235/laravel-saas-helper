<?php
namespace Faed\LaravelSaasHelper\log;
class AppendRequestIdProcessor
{
    public function __invoke($logger): void
    {
        $logger->pushProcessor(function ($record) {
            // 从请求属性中获取 request_id
            $requestId = request()->attributes->get('request_id', 'unknown');
            $record['message'] = '['.$requestId.']'.$record['message'];
            return $record;
        });
    }
}

