<?php

return [
    'uri' => 'ws://mc.api.taobao.com',

    'connections' => [
        'crm' => [
            'app_key' => env('TAOBAO_CRM_APP_KEY', 'APP KEY'),
            'app_secret' => env('TAOBAO_CRM_APP_SECRET', 'APP SECRET'),
            'group' => 'default',
            'handler' => Hucsuper\TaobaoTmc\Handler\ExampleMessageHandler::class,
            'debug' => true,
        ],
    ]
];