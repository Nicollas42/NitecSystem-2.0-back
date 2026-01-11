<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    // 1. Mantemos as rotas liberadas
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', '*'],

    'allowed_methods' => ['*'],

    // 2. AQUI ESTÁ A ATUALIZAÇÃO: Adicionamos seu domínio profissional
    'allowed_origins' => [
        // Domínios Profissionais (Principal e WWW)
        'https://nitec.dev.br',
        'https://www.nitec.dev.br',

        // Link Antigo da Vercel (Backup - caso precise acessar pelo link original)
        'https://nitec-system-2-0-front.vercel.app',

        // Ambiente Local (Para você trabalhar no PC)
        'http://localhost:5173',
        'http://127.0.0.1:5173'
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Importante manter true para login funcionar
    'supports_credentials' => true,

];