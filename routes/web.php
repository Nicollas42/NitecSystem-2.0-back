<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\PwaController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/meu-app', [PwaController::class, 'carregar_app']);

// --- ROTA DE MANUTENÇÃO (RENDER FREE) ---
// Use esta rota sempre que fizer um deploy para limpar e recriar os caches
Route::get('/otimizar-sistema', function () {
    try {
        // 1. Limpa todos os caches antigos
        Artisan::call('optimize:clear');
        
        // 2. Recria os caches para produção (Melhora performance em servidores fracos)
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        
        return "
            <div style='font-family: sans-serif; text-align: center; padding: 50px;'>
                <h1 style='color: #10b981;'>Sistema Otimizado com Sucesso! 🚀</h1>
                <p>Configurações, Rotas e Views foram cacheadas.</p>
                <hr>
                <small>Memória liberada e performance ajustada para o Render.</small>
            </div>
        ";
    } catch (\Exception $e) {
        return "
            <div style='font-family: sans-serif; text-align: center; padding: 50px;'>
                <h1 style='color: #ef4444;'>Erro ao Otimizar ❌</h1>
                <p>" . $e->getMessage() . "</p>
            </div>
        ";
    }
});