<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Class PwaController
 * Responsável por gerenciar as rotas de visualização do PWA.
 */
class PwaController extends Controller
{
    /**
     * Carrega a página principal do aplicativo.
     *
     * @return \Illuminate\View\View
     */
    public function carregar_app()
    {
        // Aqui você futuramente pode buscar dados do usuário logado
        // Exemplo: $usuario = auth()->user();
        
        return view('app_home'); 
    }
}