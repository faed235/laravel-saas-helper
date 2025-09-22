<?php


use Carbon\Carbon;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

if (!function_exists('getRequestPageSize')){

    /**
     * @return int
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    function getRequestPageSize(): int
    {
        return (int)request()->get('pageSize',10);
    }


}

if (!function_exists('generateDateRange')){
    /**
     * 生成两个日期之间的所有日期数组（包含起始和结束日期）
     * @param string $start 起始日期，格式为 'Y-m-d'（例如：'2023-01-01'）
     * @param string $end   结束日期，格式为 'Y-m-d'（例如：'2023-01-31'）
     * @return array 返回包含所有日期的数组，格式为 ['Y-m-d', 'Y-m-d', ...]
     * @throws Exception 如果日期解析失败或起始日期大于结束日期
     */
    // 改进版：添加参数校验和异常处理
    function generateDateRange(string $start, string $end): array
    {
        // 验证日期格式
        if (!strtotime($start) || !strtotime($end)) {
            throw new InvalidArgumentException('Invalid date format, expected Y-m-d');
        }

        // 确保起始日期不大于结束日期
        if (strtotime($start) > strtotime($end)) {
            return [];
            // 或者抛出异常：throw new InvalidArgumentException('Start date must be before end date');
        }

        $date = [];
        $current = $start;

        while (strtotime($current) <= strtotime($end)) {
            $date[] = $current;
            $current = Carbon::parse($current)->addDay()->format('Y-m-d');
        }

        return $date;
    }
}