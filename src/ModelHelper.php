<?php

namespace Faed\LaravelSaasHelper;

use Illuminate\Database\Eloquent\Builder;
/**
 * @method static Builder search($value, $field, $operation = '=')
 * @method static Builder searchIn($value, $field, $operation = '=')
 * @method static Builder searchLike($value,$field)
 * @method static Builder searchTime($startTime = null , $endTime = null, string $field = 'created_at')
 * @method static Builder fieldDate($value,string $field = 'created_at')
 * @method static Builder startTime($value,string $field = 'created_at')
 * @method static Builder endTime($value,string $field = 'created_at')
 */
trait ModelHelper
{
    /**
     * @param Builder $builder
     * @param $value
     * @param $field
     * @param string $operation
     * @return Builder|mixed
     */
    public function scopeSearch(Builder $builder, $value, $field, string $operation='=')
    {
        return $builder->when($value,function (Builder $builder) use ($value,$field,$operation){
            $builder->where($field,$operation,$value);
        });
    }



    public function scopeSearchIn(Builder $builder, $value, $field)
    {
        return $builder->when($value,function (Builder $builder) use ($value,$field){
            if (is_array($value)){
                $builder->whereIn($field,$value);
            }else{
                $builder->whereIn($field,explode(',',$value));
            }
        });
    }

    /**
     * 指定模糊搜索
     * @param Builder $builder
     * @param $value
     * @param $field
     * @return Builder|mixed
     */
    public function scopeSearchLike(Builder $builder,$value,$field)
    {
        return $builder->when($value,function (Builder $builder,$value) use ($field){
            $builder->where($field,'like',"%{$value}%");
        });
    }

    /**
     * 时间搜索
     * @param Builder $builder
     * @param $startTime
     * @param $endTime
     * @param string $field
     * @return mixed
     */
    public function scopeSearchTime(Builder $builder,$startTime = null , $endTime = null, string $field = 'created_at')
    {
        return $builder->startTime($startTime,$field)->endTime($endTime,$field);
    }
}