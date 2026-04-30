<?php

namespace Hucsuper\TaobaoTmc;

use Hucsuper\TaobaoTmc\Console\TmcClientCommand;
use Illuminate\Console\Application as Artisan;
use Illuminate\Support\ServiceProvider;

class TmcClientServiceProvider extends ServiceProvider
{

    /**
     * @return void
     */
    public function register()
    {
        $this->registerCommand();
    }

    /**
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