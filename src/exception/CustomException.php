<?php

namespace Faed\LaravelSaasHelper\exception;

use Faed\LaravelSaasHelper\log\AppendRequestIdProcessor;
use Illuminate\Http\JsonResponse;
use Throwable;
class CustomException extends \RuntimeException
{

    function __construct($message = '', $code = 400, Throwable $previous = null){
        parent::__construct($message, $code, $previous);
    }

    public function render($request): JsonResponse
    {
        return response()->json(array_filter(['error'=>$this->getMessage(),'code'=>999999,'trace'=>AppendRequestIdProcessor::getOrSet()]),400);
    }
}