<?php

declare(strict_types=1);
namespace Faed\LaravelSaasHelper;
use InvalidArgumentException;
use RuntimeException;
use JsonSerializable;
/**
 * 流畅接口计算器（PHP 7.2 兼容版）
 * 支持链式调用、高精度计算（BC Math）、结果冻结、JSON 序列化
 */
class FluentCalculator implements JsonSerializable
{
    /** @var string 当前计算值（字符串形式，避免浮点精度问题） */
    private $value;

    /** @var bool 是否冻结结果（禁止后续修改） */
    private $frozen = false;

    /** @var bool 是否启用 BC Math 扩展 */
    private static $useBcMath = true;

    /** @var int|null BC Math 的小数精度 */
    private static $bcMathScale = null;

    /**
     * 私有构造方法（通过 init() 创建实例）
     * @param string|int|float $value 初始值
     */
    private function __construct($value)
    {
        $this->value = (string)$value;

        // 动态检测 BC Math 是否可用
        if (self::$useBcMath && !function_exists('bcadd')) {
            self::$useBcMath = false;
        }
    }

    /**
     * 初始化计算器
     * @param string|int|float $value 初始值
     * @return self
     */
    public static function init($value): self
    {
        return new self($value);
    }

    /**
     * 配置 BC Math 使用
     * @param bool $use 是否启用
     * @param int|null $scale 计算精度（小数位数）
     */
    public static function useBcMath(bool $use = true, int $scale = null): void
    {
        self::$useBcMath = $use && function_exists('bcadd');
        self::$bcMathScale = $scale;
    }

    /**
     * 冻结当前结果（禁止后续修改）
     * @return self
     */
    public function freeze(): self
    {
        $this->frozen = true;
        return $this;
    }

    /**
     * 确保对象可修改（未冻结时抛出异常）
     * @throws RuntimeException
     */
    private function ensureMutable(): void
    {
        if ($this->frozen) {
            throw new RuntimeException('计算结果已冻结，无法修改');
        }
    }

    /**
     * 获取 BC Math 的小数精度
     * @return int
     */
    private function getScale(): int
    {
        return self::$bcMathScale !== null ? self::$bcMathScale : 10;
    }

    /**
     * 标准化输入值为字符串
     * @param string|int|float $number
     * @return string
     */
    private function normalize($number): string
    {
        return (string)$number;
    }

    // ------- 基础运算方法 -------

    /**
     * 加法
     * @param string|int|float $number
     * @return self
     */
    public function add($number): self
    {
        $this->ensureMutable();
        $normalized = $this->normalize($number);
        $this->value = self::$useBcMath
            ? bcadd($this->value, $normalized, $this->getScale())
            : (string)($this->value + $normalized);
        return $this;
    }

    /**
     * 减法
     * @param string|int|float $number
     * @return self
     */
    public function sub($number): self
    {
        $this->ensureMutable();
        $normalized = $this->normalize($number);
        $this->value = self::$useBcMath
            ? bcsub($this->value, $normalized, $this->getScale())
            : (string)($this->value - $normalized);
        return $this;
    }

    /**
     * 乘法
     * @param string|int|float $number
     * @return self
     */
    public function mul($number): self
    {
        $this->ensureMutable();
        $normalized = $this->normalize($number);
        $this->value = self::$useBcMath
            ? bcmul($this->value, $normalized, $this->getScale())
            : (string)($this->value * $normalized);
        return $this;
    }

    /**
     * 除法
     * @param string|int|float $number
     * @return self
     * @throws InvalidArgumentException
     */
    public function div($number): self
    {
        $this->ensureMutable();
        $normalized = $this->normalize($number);

        if ($normalized === '0') {
            throw new InvalidArgumentException('除数不能为零');
        }

        $this->value = self::$useBcMath
            ? bcdiv($this->value, $normalized, $this->getScale())
            : (string)($this->value / $normalized);
        return $this;
    }

    // ------- 工具方法 -------

    /**
     * 计算数组总和
     * @param array $numbers 数值数组
     * @return self
     */
    public static function sum(array $numbers): self
    {
        $sum = '0';
        $calculator = new self('0');

        foreach ($numbers as $number) {
            $sum = $calculator->add($number)->getValue();
        }

        return new self($sum);
    }

    /**
     * 获取原始值（字符串形式）
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * 获取格式化结果
     * @param int $decimals 小数位数
     * @return string
     * @throws InvalidArgumentException
     */
    public function getResult(int $decimals = 2): string
    {
        if ($decimals < 0) {
            throw new InvalidArgumentException('小数位数不能为负数');
        }

        $value = $this->value;

        // 普通模式使用 number_format
        return number_format((float)$value, $decimals, '.', '');
    }

    // ------- 接口实现 -------

    /**
     * JsonSerializable 接口方法
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->getValue(),
            'result' => $this->getResult(),
        ];
    }

    /**
     * 魔术方法：克隆时解冻对象
     */
    public function __clone()
    {
        $this->frozen = false;
    }

    /**
     * 魔术方法：直接输出结果
     * @return string
     */
    public function __toString(): string
    {
        return $this->getResult();
    }
}
