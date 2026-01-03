<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use Illuminate\Http\Request;

class ProdutoController extends Controller
{
    /**
     * Lista todos os produtos cadastrados.
     * Retorna JSON para a tabela do Vue.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function listar_produtos()
    {
        // Pega todos os produtos ordenados pelos mais recentes
        $produtos = Produto::orderBy('created_at', 'desc')->get();
        
        return response()->json($produtos);
    }

    /**
     * Cria um novo produto no banco de dados.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function criar_produto(Request $request)
    {
        // 1. Validação simples
        $request->validate([
            'nome' => 'required|string|max:150',
            'unidade_medida' => 'required|string|max:5',
            'tipo_item' => 'required|in:REVENDA,INTERNO,INSUMOS',
        ]);

        // 2. Criação no Banco
        try {
            $produto = Produto::create($request->all());

            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Produto cadastrado com sucesso!',
                'dados' => $produto
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Erro ao salvar produto: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Atualiza os dados de um produto (usado pela Ficha Técnica para salvar preço).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function atualizar_produto(Request $request, $id)
    {
        // Busca o produto pelo ID manual que você definiu
        $produto = Produto::find($id);

        if (!$produto) {
            return response()->json(['mensagem' => 'Produto não encontrado no banco.'], 404);
        }

        // Validação simples (opcional, mas recomendada)
        // Permite atualizar qualquer campo enviado, mas focamos em preços aqui
        $dados = $request->only([
            'preco_custo', 
            'preco_venda', 
            'estoque_atual', 
            'estoque_minimo',
            'nome',
            'categoria'
        ]);

        // Atualiza no banco
        $produto->update($dados);

        return response()->json([
            'status' => 'sucesso',
            'mensagem' => 'Produto atualizado com sucesso!',
            'dados' => $produto
        ]);
    }
}