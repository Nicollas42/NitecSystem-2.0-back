<?php

use Illuminate\Http\Request; // <--- Importante para evitar Erro 500
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\EquipamentoController;
use App\Http\Controllers\LojaController;
use App\Http\Controllers\ProducaoController;
/*
|--------------------------------------------------------------------------
| ROTAS PÚBLICAS (Não precisa de token/login)
|--------------------------------------------------------------------------
*/
Route::post('/cadastrar', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/esqueci-senha', [PasswordResetController::class, 'enviar_link_recuperacao']);
Route::post('/redefinir-senha-final', [PasswordResetController::class, 'redefinir_senha_final']);

// Rota de Diagnóstico
Route::get('/diagnostico', function () {
    return response()->json(['status' => 'API Online']);
});


/*
|--------------------------------------------------------------------------
| ROTAS PROTEGIDAS (Precisa estar logado)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
Route::get('/equipamentos', [EquipamentoController::class, 'index']);
Route::post('/equipamentos', [EquipamentoController::class, 'store']);
Route::get('/produtos', [ProdutoController::class, 'listar_produtos']);
Route::post('/produtos', [ProdutoController::class, 'criar_produto']);
Route::put('/produtos/{id}', [ProdutoController::class, 'atualizar_produto']);
Route::get('/minhas-lojas', [LojaController::class, 'index']);
Route::post('/verificar-senha', [AuthController::class, 'verificar_senha']);
Route::put('/lojas/{id}', [LojaController::class, 'update']);
Route::get('/estoque-geral', [ProdutoController::class, 'listar_estoque_geral']);
Route::get('/produtos/{id}/ficha', [ProdutoController::class, 'obter_detalhes_ficha']);
Route::put('/produtos/{id}/ficha', [ProdutoController::class, 'salvar_ficha_completa']);
Route::post('/producao', [ProducaoController::class, 'registrar']);
});