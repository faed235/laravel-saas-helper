<?php

namespace Faed\LaravelSaasHelper\command;

use DirectoryIterator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class FaedApifoxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faed:apifox';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '发布到apifox';

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
        Artisan::call('l5-swagger:generate');

        $apifoxProjectId  = config('laravel-saas-helper.apifox.apifox_project_id',0);
        $apifoxVersion  = config('laravel-saas-helper.apifox.apifox_version',0);
        $apifoxToken  = config('laravel-saas-helper.apifox.apifox_token',0);

        $directory = storage_path('api-docs');
        $iterator = new DirectoryIterator($directory);
        $files = [];
        foreach ($iterator as $fileinfo) {
            // 检查是否是文件（不是目录或链接等）
            if ($fileinfo->isFile()) {
                $file = $fileinfo->getPathname();
                $this->info(sprintf('上传的文件:%s',$file));
                $files[] = $fileinfo->getPathname();
            }
        }
        $url = sprintf('https://api.apifox.com/v1/projects/%d/import-openapi',$apifoxProjectId);

        foreach ($files as $file){
            $json = file_get_contents($file);
            $apiArray = json_decode($json,true);
            $client = new Client();
            $response = $client->post($url,[
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'X-Apifox-Api-Version'=>$apifoxVersion,
                    'Authorization'=>$apifoxToken,
                ],
                'json'=>[
                    'input'=>json_encode($apiArray),
                ],
            ]);
            $content = $response->getBody()->getContents();
            foreach (json_decode($content,true)['data']['counters'] as $key=>$value){
                $this->info($key.'=>'.$value);
            }
        }
        return 0;
    }
}
