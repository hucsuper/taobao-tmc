<?php

namespace Hucsuper\TaobaoTmc;

use Hucsuper\TaobaoTmc\Console\TmcClientCommand;
use Illuminate\Console\Application as Artisan;
use Illuminate\Support\ServiceProvider;

class TmcClientServiceProvider extends ServiceProvider
{

    /**
     * Author：胡超
     * 
     * Date: 2024/6/24 16:13
     * @return void
     */
    public function register()
    {
        $this->registerCommand();
    }

    /**
     * Author：胡超
     * 
     * Date: 2024/6/24 14:35
     * @return void
     */
    protected function registerCommand()
    {
        $commands = [
            TmcClientCommand::class,
        ];
        Artisan::starting(function ($artisan) use ($commands) {
            $artisan->resolveCommands($commands);
        });
    }
}