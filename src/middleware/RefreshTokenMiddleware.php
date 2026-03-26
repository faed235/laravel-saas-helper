<?php

namespace Faed\LaravelSaasHelper\middleware;

use Faed\LaravelSaasHelper\exception\CustomException;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Closure;

class RefreshTokenMiddleware extends BaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $role
     * @return mixed
     * @throws ErrorException
     * @throws JWTException
     * @throws UserException
     */
    public function handle(Request $request, Closure $next,$role='user')
    {
        try {
            $this->checkForToken($request);
        }catch (\Exception $exception){
            throw new CustomException('未提供令牌',100005);
        }

        try {
            if ($this->auth->parseToken()->check()) {
                return $next($request);
            }
            throw new CustomException('token异常',100008);
        } catch (TokenExpiredException $exception) {
            throw new CustomException('登录过期,重新登录',100007);
        }

    }
}
