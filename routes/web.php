<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PwaController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/meu-app', [PwaController::class, 'carregar_app']);