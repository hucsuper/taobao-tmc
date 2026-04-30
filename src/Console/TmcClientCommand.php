<?php

namespace Hucsuper\TaobaoTmc\Console;

use Hucsuper\TaobaoTmc\TmcClient;
use Illuminate\Console\Command;
use Workerman\Worker;

class TmcClientCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tmc:client {action : action} {connection : connection} {--d : daemon mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '开启TMC客户端';

    /**
     * @return void
     */
    public function handle()
    {
        $connections = config('tmc.connections');
        $connection = $this->argument('connection');

        if (!isset($connections[$connection])) {
            $this->error('未知的客户端');
            return;
        }


        $worker = new Worker();

        $worker->name = 'tmc_client-'.$connection;
        Worker::$pidFile = storage_path('tmc_client-'.$connection.'.pid');
        Worker::$logFile = storage_path('logs/tmc_client-'.$connection.'.log');

        $worker->onWorkerStart = [new TmcClient($connection), 'start'];

        Worker::runAll();
    }
}