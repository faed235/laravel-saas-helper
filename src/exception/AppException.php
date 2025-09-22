<?php

namespace Faed\LaravelSaasHelper\exception;

use Faed\LaravelSaasHelper\log\AppendRequestIdProcessor;
use Illuminate\Http\JsonResponse;
use Throwable;
class AppException extends \RuntimeException
{
    function __construct($message = '', $code = 500, Throwable $previous = null){
        parent::__construct($message, $code, $previous);
    }

    public function render($request): JsonResponse
    {
        $trace = AppendRequestIdProcessor::getOrSet();
        return response()->json(array_filter(['error'=>$this->getMessage(),'code'=>999999,'trace'=>$trace]),500);
    }
}