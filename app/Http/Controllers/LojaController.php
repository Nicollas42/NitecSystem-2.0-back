<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LojaController extends Controller
{
    public function index()
    {
        $user_id = Auth::id();
        $lojas = DB::table('lojas')->where('user_id', $user_id)->get();

        $matriz = $lojas->firstWhere('eh_matriz', 1);
        $filiais = $lojas->where('eh_matriz', 0)->values();

        return response()->json(['matriz' => $matriz, 'filiais' => $filiais]);
    }

    /**
     * NOVA FUNÇÃO: Atualiza os dados de uma loja específica
     */
    public function update(Request $request, $id)
    {
        $user_id = Auth::id();

        // Atualiza apenas se a loja pertencer ao usuário logado
        $afetados = DB::table('lojas')
            ->where('id', $id)
            ->where('user_id', $user_id)
            ->update([
                'nome_fantasia' => $request->nome_fantasia,
                'cnpj'          => $request->documento, // Mapeia 'documento' do front para 'cnpj'
                'telefone'      => $request->telefone,  // Adicionado telefone
                'endereco'      => $request->endereco,
                'updated_at'    => now()
            ]);

        // Retorna 200 mesmo se não houver mudanças (dados iguais)
        return response()->json(['message' => 'Dados atualizados com sucesso!']);
    }
}