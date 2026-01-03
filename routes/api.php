<?php

use Illuminate\Http\Request; // <--- Importante para evitar Erro 500
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;

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
});