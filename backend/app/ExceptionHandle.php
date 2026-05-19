<?php
declare(strict_types=1);

namespace app;

use think\exception\Handle;
use think\exception\HttpException;
use think\exception\ValidateException;
use think\Response;
use Throwable;

class ExceptionHandle extends Handle
{
    /**
     * 不需要记录信息（日志）的异常类列表
     */
    protected $ignoreReport = [
        HttpException::class,
        ValidateException::class,
    ];

    /**
     * 记录异常信息（包括日志或者其它方式记录）
     */
    public function report(Throwable $exception): void
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): Response
    {
        // 添加自定义异常处理机制
        return parent::render($request, $e);
    }
}
