<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Fornecedor;
use Illuminate\Support\Facades\DB;

class FornecedorController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // Retorna fornecedores da empresa (visível para todas as lojas)
        return Fornecedor::where('empresa_id', $user->empresa_id)
            ->orderBy('nome_fantasia')
            ->get();
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $request->validate(['nome_fantasia' => 'required']);

        $fornecedor = new Fornecedor();
        $fornecedor->empresa_id = $user->empresa_id;
        $fornecedor->nome_fantasia = $request->nome_fantasia;
        $fornecedor->razao_social = $request->razao_social;
        $fornecedor->cnpj = $request->cnpj;
        $fornecedor->telefone = $request->telefone;
        $fornecedor->vendedor_nome = $request->vendedor_nome;
        $fornecedor->save();

        return response()->json($fornecedor);
    }
    
    // Buscar histórico de compras de um produto específico para comparar preços
    public function historico_compras_produto($produtoId) 
    {
        $historico = DB::table('historico_movimentacoes')
            ->join('fornecedores', 'historico_movimentacoes.fornecedor_id', '=', 'fornecedores.id')
            ->where('historico_movimentacoes.produto_id', $produtoId)
            ->where('historico_movimentacoes.tipo_operacao', 'ENTRADA') // Apenas compras
            ->whereNotNull('historico_movimentacoes.fornecedor_id')
            ->select(
                'historico_movimentacoes.created_at as data',
                'historico_movimentacoes.quantidade',
                'historico_movimentacoes.custo_momento as valor_pago',
                'fornecedores.nome_fantasia as fornecedor'
            )
            ->orderBy('historico_movimentacoes.created_at', 'desc')
            ->limit(10)
            ->get();
            
        return response()->json($historico);
    }

    public function update(Request $request, $id)
    {
        // Garante que só edita fornecedor da própria empresa
        $fornecedor = Fornecedor::where('empresa_id', Auth::user()->empresa_id)->findOrFail($id);
        $fornecedor->update($request->all());
        return response()->json($fornecedor);
    }
}