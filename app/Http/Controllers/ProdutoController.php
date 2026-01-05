<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Produto;

class ProdutoController extends Controller
{
    /**
     * ABA "ESTOQUE ATUAL": 
     * Mostra APENAS produtos que têm vínculo com a loja selecionada.
     */
    public function listar_produtos(Request $request)
    {
        $user = Auth::user();
        $lojaId = $request->query('loja_id');

        if (!$lojaId) return response()->json([]);

        $produtos = DB::table('produtos')
            ->join('estoque_lojas', function($join) use ($lojaId) {
                $join->on('produtos.id', '=', 'estoque_lojas.produto_id')
                     ->where('estoque_lojas.loja_id', '=', $lojaId);
            })
            ->where('produtos.empresa_id', $user->empresa_id)
            ->select(
                'produtos.id',
                'produtos.nome',
                'produtos.codigo_barras',
                'produtos.codigo_balanca',
                'produtos.unidade_medida',
                'produtos.tipo_item',
                'produtos.categoria',
                'produtos.grupo_familia',
                
                // DADOS FINANCEIROS
                DB::raw('COALESCE(estoque_lojas.preco_custo, produtos.preco_custo) as preco_custo'),
                'estoque_lojas.preco_venda', 
                
                // DADOS DE ESTOQUE (SEPARADOS)
                'estoque_lojas.quantidade as estoque_deposito', // Tratamos 'quantidade' como Depósito
                'estoque_lojas.quantidade_vitrine as estoque_vitrine', // Nova coluna
                
                'estoque_lojas.estoque_minimo',
                'estoque_lojas.validade'
            )
            ->orderBy('produtos.nome', 'asc')
            ->get();

        return response()->json($produtos);
    }

    /**
     * ABA "ESTOQUE GERAL":
     * Mostra o estoque de TODAS as filiais.
     */
    public function listar_estoque_geral(Request $request)
    {
        $user = Auth::user();

        $geral = DB::table('estoque_lojas')
            ->join('produtos', 'estoque_lojas.produto_id', '=', 'produtos.id')
            ->join('lojas', 'estoque_lojas.loja_id', '=', 'lojas.id')
            ->where('produtos.empresa_id', $user->empresa_id)
            ->select(
                'produtos.id as prod_id',
                'produtos.nome as produto_nome',
                'produtos.categoria',
                'produtos.codigo_barras',
                'produtos.codigo_balanca',
                'produtos.unidade_medida', 
                'lojas.nome_fantasia as filial_nome',
                'lojas.eh_matriz',
                'estoque_lojas.preco_venda',
                'estoque_lojas.validade',
                DB::raw('COALESCE(estoque_lojas.preco_custo, produtos.preco_custo) as preco_custo'),
                'estoque_lojas.quantidade as estoque_deposito',
                'estoque_lojas.quantidade_vitrine as estoque_vitrine'
            )
            ->orderBy('produtos.nome', 'asc') 
            ->orderBy('lojas.eh_matriz', 'desc')
            ->get();

        return response()->json($geral);
    }

    /**
     * CRIAR PRODUTO
     */
    public function criar_produto(Request $request)
    {
        return $this->salvar_produto($request, null);
    }
    
    /**
     * ATUALIZAR PRODUTO
     */
    public function atualizar_produto(Request $request, $id)
    {
        return $this->salvar_produto($request, $id);
    }

    /**
     * Lógica Unificada de Salvamento (DRY)
     */
    private function salvar_produto($request, $id = null)
    {
        $user = Auth::user();
        $lojaId = $request->loja_id; 

        // Validação básica
        if (!$id) {
            $request->validate([
                'nome' => 'required|string|max:150',
                'loja_id' => 'required',
            ]);
        }

        try {
            DB::beginTransaction();

            // 1. DADOS GLOBAIS (CATÁLOGO)
            $dadosGlobal = $request->only(['nome', 'codigo_barras', 'codigo_balanca', 'categoria', 'unidade_medida', 'grupo_familia', 'tipo_item']);
            
            // Define valores padrão se vierem vazios
            if (!$request->categoria) $dadosGlobal['categoria'] = 'Outros';
            if (!$request->tipo_item) $dadosGlobal['tipo_item'] = 'REVENDA';

            if ($id) {
                // UPDATE
                Produto::where('id', $id)->where('empresa_id', $user->empresa_id)->update($dadosGlobal);
                $produtoId = $id;
            } else {
                // CREATE
                $dadosGlobal['empresa_id'] = $user->empresa_id;
                $dadosGlobal['created_at'] = now();
                $dadosGlobal['updated_at'] = now();
                // Preços globais padrão (zerados, pois valem os da loja)
                $dadosGlobal['preco_venda'] = 0;
                $dadosGlobal['preco_custo'] = 0;
                
                if ($request->id) $dadosGlobal['id'] = $request->id; // ID Manual
                
                $produtoId = DB::table('produtos')->insertGetId($dadosGlobal);
            }

            // 2. DADOS LOCAIS (ESTOQUE LOJA)
            if ($lojaId) {
                $dadosLocal = [
                    'preco_venda'    => $request->filled('preco_venda') ? (float)$request->preco_venda : 0,
                    'preco_custo'    => $request->filled('preco_custo') ? (float)$request->preco_custo : 0,
                    'estoque_minimo' => $request->filled('estoque_minimo') ? (float)$request->estoque_minimo : 5,
                    'validade'       => $request->validade,
                    
                    // AQUI SALVAMOS OS DOIS ESTOQUES
                    'quantidade'         => $request->filled('estoque_deposito') ? (float)$request->estoque_deposito : 0,
                    'quantidade_vitrine' => $request->filled('estoque_vitrine') ? (float)$request->estoque_vitrine : 0,
                    
                    'updated_at'     => now()
                ];

                // REGRA DE NEGÓCIO: Se for INTERNO, zera dados financeiros e estoques (gerido na Ficha)
                if ($request->tipo_item === 'INTERNO') {
                    $dadosLocal['preco_venda'] = 0;
                    $dadosLocal['preco_custo'] = 0;
                    $dadosLocal['quantidade'] = 0;
                    $dadosLocal['quantidade_vitrine'] = 0;
                    $dadosLocal['validade'] = null;
                }

                if (!$id) $dadosLocal['created_at'] = now(); // Apenas no insert

                DB::table('estoque_lojas')->updateOrInsert(
                    ['loja_id' => $lojaId, 'produto_id' => $produtoId],
                    $dadosLocal
                );
            }

            DB::commit();
            return response()->json(['status' => 'sucesso', 'mensagem' => 'Produto salvo com sucesso!']);

        } catch (\Exception $e) {
            DB::rollBack();
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json(['status' => 'erro', 'mensagem' => 'ID ou Código de Barras já existente.'], 400);
            }
            return response()->json(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }
}