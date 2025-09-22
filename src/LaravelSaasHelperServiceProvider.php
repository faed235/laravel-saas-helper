<?php
namespace Faed\LaravelSaasHelper;

use Faed\LaravelSaasHelper\command\FaedApifoxCommand;
use Faed\LaravelSaasHelper\command\FaedParameterCommand;
use Illuminate\Support\ServiceProvider;
class LaravelSaasHelperServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //配置文件
        $this->publishes([
            $this->configPath() => config_path('laravel-saas-helper.php'),
        ]);

        //发布cli命令行
        if ($this->app->runningInConsole()) {
            $this->commands([
                FaedParameterCommand::class,
                FaedApifoxCommand::class,
            ]);
        }

    }

    public function register()
    {
        $this->mergeConfigFrom($this->configPath(), 'laravel-saas-helper');
    }
    /**
     * Set the config path
     *
     * @return string
     */
    protected function configPath(): string
    {
        return __DIR__ . '/config/laravel-saas-helper.php';
    }
}