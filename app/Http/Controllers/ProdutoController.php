<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Produto;
use Illuminate\Support\Facades\Storage;

class ProdutoController extends Controller
{
    /**
     * ABA "ESTOQUE ATUAL": 
     * Lista todos os produtos da empresa e anexa os lotes detalhados de cada um.
     */
    public function listar_produtos(Request $request)
    {
        $user = Auth::user();
        $loja_id = $request->query('loja_id');

        if (!$loja_id) {
            return response()->json([]);
        }

        $produtos = DB::table('produtos')
            ->leftJoin('estoque_lojas', function($join) use ($loja_id) {
                $join->on('produtos.id', '=', 'estoque_lojas.produto_id')
                     ->where('estoque_lojas.loja_id', '=', $loja_id);
            })
            ->leftJoin('fornecedores', 'produtos.fornecedor_id', '=', 'fornecedores.id')
            ->where('produtos.empresa_id', $user->empresa_id)
            ->select(
                'produtos.id',
                'produtos.nome',
                'produtos.imagem_path',
                'produtos.codigo_barras',
                'produtos.codigo_balanca',
                'produtos.unidade_medida',
                'produtos.rendimento',
                'produtos.tipo_item',
                'produtos.categoria',
                'produtos.fornecedor_id',
                'produtos.grupo_familia',
                
                'fornecedores.nome_fantasia as fornecedor_nome',
                'fornecedores.vendedor_nome',

                DB::raw('CASE WHEN estoque_lojas.id IS NOT NULL THEN 1 ELSE 0 END as tem_cadastro'),
                DB::raw('COALESCE(estoque_lojas.preco_custo, produtos.preco_custo, 0) as preco_custo'),
                DB::raw('COALESCE(estoque_lojas.preco_venda, produtos.preco_venda, 0) as preco_venda'),
                
                DB::raw('COALESCE(estoque_lojas.margem_lucro, 0) as margem_lucro'),
                DB::raw('COALESCE(estoque_lojas.imposto_venda, 0) as imposto_venda'),
                
                DB::raw('COALESCE(estoque_lojas.quantidade, 0) as estoque_deposito'),
                DB::raw('COALESCE(estoque_lojas.quantidade_vitrine, 0) as estoque_vitrine'),
                
                DB::raw('COALESCE(estoque_lojas.estoque_minimo, 5) as estoque_minimo'),
                'estoque_lojas.validade'
            )
            ->orderBy('produtos.nome', 'asc')
            ->get();

        // MAPEIA OS PRODUTOS PARA ADICIONAR OS SUB-LOTES PARA A VISUALIZAÇÃO EXPANSÍVEL
        $produtos_com_lotes = $produtos->map(function ($produto) use ($loja_id) {
            $produto->lotes = DB::table('estoque_lotes')
                ->leftJoin('fornecedores', 'estoque_lotes.fornecedor_id', '=', 'fornecedores.id')
                ->where('estoque_lotes.produto_id', $produto->id)
                ->where('estoque_lotes.loja_id', $loja_id)
                ->where('estoque_lotes.quantidade_atual', '>', 0) // Apenas lotes com saldo positivo
                ->select(
                    'estoque_lotes.id',
                    'estoque_lotes.quantidade_atual',
                    'estoque_lotes.preco_custo',
                    'estoque_lotes.validade',
                    'estoque_lotes.created_at as data_entrada',
                    'fornecedores.nome_fantasia as fornecedor_nome'
                )
                ->orderBy('estoque_lotes.validade', 'asc')
                ->get();

            return $produto;
        });

        return response()->json($produtos_com_lotes);
    }

    /**
     * ABA "ESTOQUE GERAL"
     * Visão unificada da rede.
     */
    public function listar_estoque_geral(Request $request)
    {
        $user = Auth::user();

        $geral = DB::table('estoque_lojas')
            ->join('produtos', 'estoque_lojas.produto_id', '=', 'produtos.id')
            ->join('lojas', 'estoque_lojas.loja_id', '=', 'lojas.id')
            ->leftJoin('fornecedores', 'produtos.fornecedor_id', '=', 'fornecedores.id')
            ->where('produtos.empresa_id', $user->empresa_id)
            ->select(
                'produtos.id as prod_id',
                'produtos.nome as produto_nome',
                'produtos.imagem_path',
                'produtos.categoria',
                'produtos.codigo_barras',
                'produtos.codigo_balanca',
                'produtos.unidade_medida', 
                'fornecedores.nome_fantasia as fornecedor_nome',
                'fornecedores.vendedor_nome',
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

    public function criar_produto(Request $request) { return $this->salvar_produto($request, null); }
    public function atualizar_produto(Request $request, $id) { return $this->salvar_produto($request, $id); }

    /**
     * TRATAMENTO DE UPLOAD DE IMAGEM
     */
    private function handleImageUpload(Request $request, $produtoId = null)
    {
        if (!$request->hasFile('imagem_arquivo')) {
            return null;
        }

        $request->validate([
            'imagem_arquivo' => 'image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        $file = $request->file('imagem_arquivo');
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        
        $path = $file->storeAs('produtos', $filename, 'public');

        if ($produtoId) {
            $produtoAntigo = DB::table('produtos')->where('id', $produtoId)->first();
            if ($produtoAntigo && $produtoAntigo->imagem_path) {
                if (Storage::disk('public')->exists($produtoAntigo->imagem_path)) {
                    Storage::disk('public')->delete($produtoAntigo->imagem_path);
                }
            }
        }

        return $path;
    }

    /**
     * Lógica Unificada de Salvamento (Editada para Gestão Automática de Lotes)
     * @param Request $request
     * @param int|null $id
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
            $dadosGlobal = $request->only(['nome', 'codigo_barras', 'codigo_balanca', 'categoria', 'unidade_medida', 'rendimento', 'grupo_familia', 'tipo_item', 'fornecedor_id', 'estoque_infinito']);
            
            $dadosGlobal['estoque_infinito'] = filter_var($request->estoque_infinito, FILTER_VALIDATE_BOOLEAN);
            
            if (!$request->categoria) $dadosGlobal['categoria'] = 'Outros';
            if (!$request->tipo_item) $dadosGlobal['tipo_item'] = 'REVENDA';
            
            if (isset($dadosGlobal['rendimento']) && $dadosGlobal['rendimento'] <= 0) {
                $dadosGlobal['rendimento'] = 1;
            }

            $novoPath = $this->handleImageUpload($request, $id);
            if ($novoPath) {
                $dadosGlobal['imagem_path'] = $novoPath;
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
                // Busca quantidade anterior para calcular a diferença e gerar lote de ajuste
                $qtdAnterior = DB::table('estoque_lojas')
                    ->where('loja_id', $lojaId)
                    ->where('produto_id', $produtoId)
                    ->value('quantidade') ?? 0;

                $dadosLocal = ['updated_at' => now()];

                if ($request->has('preco_venda')) $dadosLocal['preco_venda'] = (float)$request->preco_venda;
                if ($request->has('preco_custo')) $dadosLocal['preco_custo'] = (float)$request->preco_custo;
                if ($request->has('estoque_minimo')) $dadosLocal['estoque_minimo'] = (float)$request->estoque_minimo;
                if ($request->has('validade')) $dadosLocal['validade'] = $request->validade;
                if ($request->has('margem_lucro')) $dadosLocal['margem_lucro'] = (float)$request->margem_lucro;
                if ($request->has('imposto_venda')) $dadosLocal['imposto_venda'] = (float)$request->imposto_venda;
                if ($request->has('estoque_deposito')) $dadosLocal['quantidade'] = (float)$request->estoque_deposito;
                if ($request->has('estoque_vitrine')) $dadosLocal['quantidade_vitrine'] = (float)$request->estoque_vitrine;

                if (!$id) {
                    $dadosLocal['created_at'] = now();
                    if (!isset($dadosLocal['quantidade'])) $dadosLocal['quantidade'] = 0;
                    if (!isset($dadosLocal['quantidade_vitrine'])) $dadosLocal['quantidade_vitrine'] = 0;
                }

                DB::table('estoque_lojas')->updateOrInsert(
                    ['loja_id' => $lojaId, 'produto_id' => $produtoId],
                    $dadosLocal
                );

                // --- GESTÃO AUTOMÁTICA DE LOTES (AJUSTE MANUAL/INICIAL) ---
                if ($request->has('estoque_deposito')) {
                    $novaQtd = (float)$request->estoque_deposito;

                    if ($novaQtd > $qtdAnterior) {
                        // Se houve aumento manual de estoque, cria um lote de ajuste para a diferença positiva
                        $diferenca = $novaQtd - $qtdAnterior;
                        DB::table('estoque_lotes')->insert([
                            'loja_id' => $lojaId,
                            'produto_id' => $produtoId,
                            'fornecedor_id' => $request->fornecedor_id ?? null,
                            'quantidade_inicial' => $diferenca,
                            'quantidade_atual' => $diferenca,
                            'preco_custo' => (float)($request->preco_custo ?? 0),
                            'validade' => $request->validade,
                            'numero_lote' => 'AJUSTE-MANUAL-' . date('Ymd-His'),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    } elseif ($novaQtd < $qtdAnterior) {
                        // Se houve redução manual (ajuste negativo), consome dos lotes existentes (menor quantidade primeiro)
                        $reducao = $qtdAnterior - $novaQtd;
                        $this->baixar_lotes_ajuste($lojaId, $produtoId, $reducao);
                    }
                }
            }

            DB::commit();
            return response()->json(['status' => 'sucesso', 'mensagem' => 'Produto e estoque atualizados com sucesso!']);

        } catch (\Exception $e) {
            DB::rollBack();
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json(['status' => 'erro', 'mensagem' => 'ID ou Código de Barras já existente.'], 400);
            }
            return response()->json(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    /**
     * Consome os lotes para manter o estoque sincronizado após redução manual.
     * @param int $lojaId
     * @param int $produtoId
     * @param float $quantidade
     */
    private function baixar_lotes_ajuste($lojaId, $produtoId, $quantidade)
    {
        $lotes = DB::table('estoque_lotes')
            ->where('loja_id', $lojaId)
            ->where('produto_id', $produtoId)
            ->where('quantidade_atual', '>', 0)
            ->orderBy('quantidade_atual', 'asc') // Sua regra de negócio: consome o menor lote primeiro
            ->get();

        $restante = $quantidade;
        foreach ($lotes as $lote) {
            if ($restante <= 0) break;
            $baixa = min($lote->quantidade_atual, $restante);
            DB::table('estoque_lotes')->where('id', $lote->id)->decrement('quantidade_atual', $baixa);
            $restante -= $baixa;
        }
    }

    /**
     * FICHA TÉCNICA: MÉTODOS MANTIDOS COMPLETOS
     */
    public function obter_detalhes_ficha($id, Request $request)
    {
        $lojaId = $request->query('loja_id');
        $produto = DB::table('produtos')
            ->leftJoin('estoque_lojas', function($join) use ($lojaId) {
                $join->on('produtos.id', '=', 'estoque_lojas.produto_id')
                     ->where('estoque_lojas.loja_id', '=', $lojaId);
            })
            ->where('produtos.id', $id)
            ->select(
                'produtos.id', 'produtos.rendimento', 'produtos.fornecedor_id', 'produtos.imagem_path',
                DB::raw('COALESCE(estoque_lojas.preco_venda, produtos.preco_venda, 0) as preco_venda')
            )->first();

        $ingredientes = DB::table('ficha_tecnica_ingredientes')
            ->join('produtos', 'ficha_tecnica_ingredientes.insumo_id', '=', 'produtos.id')
            ->leftJoin('estoque_lojas', function($join) use ($lojaId) {
                $join->on('produtos.id', '=', 'estoque_lojas.produto_id')
                     ->where('estoque_lojas.loja_id', '=', $lojaId);
            })
            ->where('ficha_tecnica_ingredientes.produto_id', $id)
            ->select(
                'produtos.id', 'produtos.nome', 'produtos.unidade_medida as unidade', 'produtos.estoque_infinito', 
                DB::raw('COALESCE(estoque_lojas.preco_custo, 0) as custo_unitario'),
                DB::raw('COALESCE(estoque_lojas.quantidade, 0) as estoque_atual'), 
                'ficha_tecnica_ingredientes.qtd_usada as qtd'
            )->get();

        $maquinas = DB::table('ficha_tecnica_maquinas')
            ->join('equipamentos', 'ficha_tecnica_maquinas.equipamento_id', '=', 'equipamentos.id')
            ->where('ficha_tecnica_maquinas.produto_id', $id)
            ->select(
                'equipamentos.id', 'equipamentos.nome', 'equipamentos.potencia_watts', 'equipamentos.consumo_gas_kg_h as consumo_gas',
                'equipamentos.tipo_energia', 'equipamentos.depreciacao_hora', 'equipamentos.valor_aquisicao',
                'equipamentos.valor_residual', 'equipamentos.vida_util_anos', 'ficha_tecnica_maquinas.tempo_minutos as minutos'
            )->get();

        return response()->json(['produto' => $produto, 'ingredientes' => $ingredientes, 'maquinas' => $maquinas]);
    }

    public function salvar_ficha_completa(Request $request, $id)
    {
        $lojaId = $request->loja_id;
        try {
            DB::beginTransaction();
            Produto::where('id', $id)->update([
                'tipo_item' => 'INTERNO',
                'rendimento' => $request->rendimento > 0 ? $request->rendimento : 1,
                'updated_at' => now()
            ]);

            if ($lojaId) {
                DB::table('estoque_lojas')->updateOrInsert(
                    ['loja_id' => $lojaId, 'produto_id' => $id],
                    ['preco_custo' => $request->preco_custo, 'preco_venda' => $request->preco_venda, 'margem_lucro' => $request->margem_lucro, 'imposto_venda' => $request->imposto_venda, 'updated_at' => now()]
                );
            }

            DB::table('ficha_tecnica_ingredientes')->where('produto_id', $id)->delete();
            $ingredientesInsert = [];
            if ($request->has('ingredientes')) {
                foreach ($request->ingredientes as $ing) {
                    $ingredientesInsert[] = ['produto_id' => $id, 'insumo_id' => $ing['id'], 'qtd_usada' => $ing['qtd']];
                }
                if(count($ingredientesInsert) > 0) DB::table('ficha_tecnica_ingredientes')->insert($ingredientesInsert);
            }

            DB::table('ficha_tecnica_maquinas')->where('produto_id', $id)->delete();
            $maquinasInsert = [];
            if ($request->has('maquinas')) {
                foreach ($request->maquinas as $maq) {
                    $maquinasInsert[] = ['produto_id' => $id, 'equipamento_id' => $maq['id'], 'tempo_minutos' => $maq['minutos']];
                }
                if(count($maquinasInsert) > 0) DB::table('ficha_tecnica_maquinas')->insert($maquinasInsert);
            }

            DB::commit();
            return response()->json(['status' => 'sucesso', 'mensagem' => 'Ficha Técnica salva com sucesso!']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }
}