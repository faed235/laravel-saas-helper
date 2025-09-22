<?php

namespace Faed\LaravelSaasHelper\command;

use DirectoryIterator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FaedControllerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faed:controller
                             {name : 名称}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '创建controller和Request';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }




    /**
     * @return int
     * @throws GuzzleException
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        Artisan::call('make:controller', ['name' =>"{$name}Controller",'--api' => true]);
        Artisan::call('make:request', ['name' =>"{$name}Request"]);
        return 0;
    }
}
