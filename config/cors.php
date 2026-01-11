<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    // 1. Adicionamos 'login', 'logout' e '*' para garantir que todas as rotas passem pelo CORS
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', '*'],

    'allowed_methods' => ['*'],

    // 2. AQUI ESTÁ O SEGREDO: Troque o '*' pelo link EXATO da Vercel (sem a barra no final)
    'allowed_origins' => [
        'https://nitec-system-2-0-front.vercel.app'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];