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
     * Lista TODOS os produtos da empresa. 
     * Se não tiver estoque na loja, mostra 0.
     * Agora informa se o produto tem cadastro na loja atual (tem_cadastro = 1 ou 0).
     */
    public function listar_produtos(Request $request)
    {
        $user = Auth::user();
        $lojaId = $request->query('loja_id');

        if (!$lojaId) return response()->json([]);

        // Usar leftJoin para trazer catálogo completo, mesmo sem movimento na loja
        $produtos = DB::table('produtos')
            ->leftJoin('estoque_lojas', function($join) use ($lojaId) {
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
                'produtos.rendimento',
                'produtos.tipo_item',
                'produtos.categoria',
                'produtos.grupo_familia',
                
                // NOVO CAMPO: Indica se existe vínculo real com essa loja (1 = Sim, 0 = Não)
                DB::raw('CASE WHEN estoque_lojas.id IS NOT NULL THEN 1 ELSE 0 END as tem_cadastro'),

                // DADOS FINANCEIROS (Se null no estoque, usa o global ou 0)
                DB::raw('COALESCE(estoque_lojas.preco_custo, produtos.preco_custo, 0) as preco_custo'),
                DB::raw('COALESCE(estoque_lojas.preco_venda, produtos.preco_venda, 0) as preco_venda'),
                
                // --- CAMPOS DE PRECIFICAÇÃO ---
                DB::raw('COALESCE(estoque_lojas.margem_lucro, 0) as margem_lucro'),
                DB::raw('COALESCE(estoque_lojas.imposto_venda, 0) as imposto_venda'),
                
                // DADOS DE ESTOQUE (Se null, retorna 0)
                DB::raw('COALESCE(estoque_lojas.quantidade, 0) as estoque_deposito'),
                DB::raw('COALESCE(estoque_lojas.quantidade_vitrine, 0) as estoque_vitrine'),
                
                DB::raw('COALESCE(estoque_lojas.estoque_minimo, 5) as estoque_minimo'),
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

        // Aqui mantemos join (inner) pois queremos ver apenas onde TEM estoque físico
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

    // --- FUNÇÕES WRAPPERS ---
    public function criar_produto(Request $request) { return $this->salvar_produto($request, null); }
    public function atualizar_produto(Request $request, $id) { return $this->salvar_produto($request, $id); }

    /**
     * Lógica Unificada de Salvamento (Cadastro Básico)
     */
    private function salvar_produto($request, $id = null)
    {
        $user = Auth::user();
        $lojaId = $request->loja_id; 

        if (!$id) {
            $request->validate([
                'nome' => 'required|string|max:150',
                'loja_id' => 'required',
            ]);
        }

        try {
            DB::beginTransaction();

            // 1. DADOS GLOBAIS
            $dadosGlobal = $request->only(['nome', 'codigo_barras', 'codigo_balanca', 'categoria', 'unidade_medida', 'rendimento', 'grupo_familia', 'tipo_item']);
            
            if (!$request->categoria) $dadosGlobal['categoria'] = 'Outros';
            if (!$request->tipo_item) $dadosGlobal['tipo_item'] = 'REVENDA';
            
            if (isset($dadosGlobal['rendimento']) && $dadosGlobal['rendimento'] <= 0) {
                $dadosGlobal['rendimento'] = 1;
            }

            if ($id) {
                Produto::where('id', $id)->where('empresa_id', $user->empresa_id)->update($dadosGlobal);
                $produtoId = $id;
            } else {
                $dadosGlobal['empresa_id'] = $user->empresa_id;
                $dadosGlobal['created_at'] = now();
                $dadosGlobal['updated_at'] = now();
                $dadosGlobal['preco_venda'] = 0;
                $dadosGlobal['preco_custo'] = 0;
                
                if ($request->id) $dadosGlobal['id'] = $request->id;
                
                $produtoId = DB::table('produtos')->insertGetId($dadosGlobal);
            }

            // 2. DADOS LOCAIS (ESTOQUE LOJA)
            if ($lojaId) {
                $dadosLocal = [
                    'updated_at' => now()
                ];

                // Atualiza apenas se enviado na requisição (para não sobrescrever com null)
                if ($request->has('preco_venda')) $dadosLocal['preco_venda'] = (float)$request->preco_venda;
                if ($request->has('preco_custo')) $dadosLocal['preco_custo'] = (float)$request->preco_custo;
                if ($request->has('estoque_minimo')) $dadosLocal['estoque_minimo'] = (float)$request->estoque_minimo;
                if ($request->has('validade')) $dadosLocal['validade'] = $request->validade;

                // Salva margem e imposto se vierem
                if ($request->has('margem_lucro')) $dadosLocal['margem_lucro'] = (float)$request->margem_lucro;
                if ($request->has('imposto_venda')) $dadosLocal['imposto_venda'] = (float)$request->imposto_venda;

                if ($request->has('estoque_deposito')) $dadosLocal['quantidade'] = (float)$request->estoque_deposito;
                if ($request->has('estoque_vitrine')) $dadosLocal['quantidade_vitrine'] = (float)$request->estoque_vitrine;

                // Garante criação inicial se não existir
                if (!$id) {
                    $dadosLocal['created_at'] = now();
                    if (!isset($dadosLocal['quantidade'])) $dadosLocal['quantidade'] = 0;
                    if (!isset($dadosLocal['quantidade_vitrine'])) $dadosLocal['quantidade_vitrine'] = 0;
                }

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

    /**
     * FICHA TÉCNICA: RECUPERA INGREDIENTES E MÁQUINAS SALVOS
     */
    public function obter_detalhes_ficha($id, Request $request)
    {
        $lojaId = $request->query('loja_id');

        // 1. Busca Produto e Preço (Incluindo preço de venda da loja)
        $produto = DB::table('produtos')
            ->leftJoin('estoque_lojas', function($join) use ($lojaId) {
                $join->on('produtos.id', '=', 'estoque_lojas.produto_id')
                     ->where('estoque_lojas.loja_id', '=', $lojaId);
            })
            ->where('produtos.id', $id)
            ->select(
                'produtos.id',
                'produtos.rendimento',
                DB::raw('COALESCE(estoque_lojas.preco_venda, produtos.preco_venda, 0) as preco_venda')
            )
            ->first();

        // 2. Busca Ingredientes (Agora com Estoque Atual para validação de produção)
        $ingredientes = DB::table('ficha_tecnica_ingredientes')
            ->join('produtos', 'ficha_tecnica_ingredientes.insumo_id', '=', 'produtos.id')
            ->leftJoin('estoque_lojas', function($join) use ($lojaId) {
                $join->on('produtos.id', '=', 'estoque_lojas.produto_id')
                     ->where('estoque_lojas.loja_id', '=', $lojaId);
            })
            ->where('ficha_tecnica_ingredientes.produto_id', $id)
            ->select(
                'produtos.id',
                'produtos.nome',
                'produtos.unidade_medida as unidade',
                DB::raw('COALESCE(estoque_lojas.preco_custo, 0) as custo_unitario'),
                // Campo crucial para a aba de Produção:
                DB::raw('COALESCE(estoque_lojas.quantidade, 0) as estoque_atual'), 
                'ficha_tecnica_ingredientes.qtd_usada as qtd'
            )
            ->get();

        // 3. Busca Máquinas
        $maquinas = DB::table('ficha_tecnica_maquinas')
            ->join('equipamentos', 'ficha_tecnica_maquinas.equipamento_id', '=', 'equipamentos.id')
            ->where('ficha_tecnica_maquinas.produto_id', $id)
            ->select(
                'equipamentos.id',
                'equipamentos.nome',
                'equipamentos.potencia_watts',
                'equipamentos.consumo_gas_kg_h as consumo_gas',
                'equipamentos.tipo_energia',
                'equipamentos.depreciacao_hora',
                'equipamentos.valor_aquisicao',
                'equipamentos.valor_residual',
                'equipamentos.vida_util_anos',
                'ficha_tecnica_maquinas.tempo_minutos as minutos'
            )
            ->get();

        return response()->json([
            'produto' => $produto,
            'ingredientes' => $ingredientes,
            'maquinas' => $maquinas
        ]);
    }

    /**
     * FICHA TÉCNICA: SALVA TUDO (Produto, Estoque, Ingredientes, Máquinas)
     */
    public function salvar_ficha_completa(Request $request, $id)
    {
        $lojaId = $request->loja_id;
        
        try {
            DB::beginTransaction();

            // 1. Atualiza Produto (Tipo e Rendimento)
            Produto::where('id', $id)->update([
                'tipo_item' => 'INTERNO', // Garante que vira item de produção
                'rendimento' => $request->rendimento > 0 ? $request->rendimento : 1,
                'updated_at' => now()
            ]);

            // 2. Atualiza Estoque (Preços e Margens Calculadas)
            if ($lojaId) {
                DB::table('estoque_lojas')->updateOrInsert(
                    ['loja_id' => $lojaId, 'produto_id' => $id],
                    [
                        'preco_custo' => $request->preco_custo,
                        'preco_venda' => $request->preco_venda,
                        'margem_lucro' => $request->margem_lucro,
                        'imposto_venda' => $request->imposto_venda,
                        'updated_at' => now()
                    ]
                );
            }

            // 3. Atualiza Ingredientes (Limpa antigos e insere novos)
            DB::table('ficha_tecnica_ingredientes')->where('produto_id', $id)->delete();
            $ingredientesInsert = [];
            if ($request->has('ingredientes')) {
                foreach ($request->ingredientes as $ing) {
                    $ingredientesInsert[] = [
                        'produto_id' => $id,
                        'insumo_id' => $ing['id'],
                        'qtd_usada' => $ing['qtd']
                    ];
                }
                if(count($ingredientesInsert) > 0) {
                    DB::table('ficha_tecnica_ingredientes')->insert($ingredientesInsert);
                }
            }

            // 4. Atualiza Máquinas (Limpa antigas e insere novas)
            DB::table('ficha_tecnica_maquinas')->where('produto_id', $id)->delete();
            $maquinasInsert = [];
            if ($request->has('maquinas')) {
                foreach ($request->maquinas as $maq) {
                    $maquinasInsert[] = [
                        'produto_id' => $id,
                        'equipamento_id' => $maq['id'],
                        'tempo_minutos' => $maq['minutos']
                    ];
                }
                if(count($maquinasInsert) > 0) {
                    DB::table('ficha_tecnica_maquinas')->insert($maquinasInsert);
                }
            }

            DB::commit();
            return response()->json(['status' => 'sucesso', 'mensagem' => 'Ficha Técnica salva com sucesso!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }
}