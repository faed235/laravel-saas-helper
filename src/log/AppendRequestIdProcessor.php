<?php
namespace Faed\LaravelSaasHelper\log;
class AppendRequestIdProcessor
{
    public function __invoke($logger): void
    {
        $logger->pushProcessor(function ($record) {
            // 从请求属性中获取 request_id
            $requestId = self::getOrSet();
            $record['message'] = '['.$requestId.']'.$record['message'];
            return $record;
        });
    }


    /**
     * @return mixed|string
     */
    public static function getOrSet()
    {
        if (!($requestId = request()->attributes->get('request_id'))) {
            $requestId = uniqid();
            self::setRequestId($requestId);
        }
        return $requestId;
    }


    /**
     * @param $requestId
     * @return void
     */
    public static function setRequestId($requestId)
    {
        request()->attributes->set('request_id',$requestId);
    }

    public static function exceptionFormat($exception, array $other = []): array
    {
        return array_merge($other, [
            'msg' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }
}

