<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovimentacaoController extends Controller
{
    /**
     * REGISTRAR MOVIMENTAÇÃO
     * Suporta Entradas, Transferências Internas, Perdas e Transferências entre Filiais.
     */
    public function registrar(Request $request)
    {
        $request->validate([
            'loja_id' => 'required',
            'produto_id' => 'required',
            'acao' => 'required', 
            'quantidade' => 'required|numeric|min:0.001',
            'motivo' => 'required|string',
            'lote_id' => 'nullable|integer'
        ]);

        $lojaId = $request->loja_id;
        $prodId = $request->produto_id;
        $qtd = $request->quantidade;
        $motivo = $request->motivo;
        $acao = $request->acao;
        $loteIdManual = $request->lote_id;

        try {
            DB::beginTransaction();

            $estoque = DB::table('estoque_lojas')
                ->where('loja_id', $lojaId)
                ->where('produto_id', $prodId)
                ->first();

            if (!$estoque) throw new \Exception("Produto não cadastrado no estoque desta loja.");

            // =================================================================================
            // 🔒 TRAVA DE SEGURANÇA DE LOTE (IMPEDE NEGATIVAR LOTE ESPECÍFICO)
            // =================================================================================
            if ($loteIdManual) {
                $loteCheck = DB::table('estoque_lotes')->where('id', $loteIdManual)->first();
                
                if (!$loteCheck) {
                    throw new \Exception("O lote selecionado não existe ou já foi consumido.");
                }

                if (round($loteCheck->quantidade_atual, 3) < round($qtd, 3)) {
                    throw new \Exception("Saldo insuficiente no lote selecionado. Disponível: " . $loteCheck->quantidade_atual);
                }
            }
            // =================================================================================

            $origem = '';
            $destino = '';
            $tipoOperacao = '';

            // --- 1. ENTRADA ---
            if ($acao === 'entrada_deposito') {
                $tipoOperacao = 'ENTRADA';
                $origem = 'EXTERNO';
                $destino = 'DEPOSITO';
                DB::table('estoque_lojas')->where('id', $estoque->id)->increment('quantidade', $qtd);
                
                // Cria lote de ajuste manual para entrada
                DB::table('estoque_lotes')->insert([
                    'loja_id' => $lojaId,
                    'produto_id' => $prodId,
                    'quantidade_inicial' => $qtd,
                    'quantidade_atual' => $qtd,
                    'preco_custo' => $estoque->preco_custo, // Usa custo atual médio
                    'numero_lote' => 'AJUSTE-' . date('dmy-His'),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // --- 2. TRANSFERÊNCIAS ---
            else if ($acao === 'transf_dep_vit') {
                if ($estoque->quantidade < $qtd) throw new \Exception("Saldo geral insuficiente no Depósito.");
                
                $tipoOperacao = 'TRANSFERENCIA';
                $origem = 'DEPOSITO';
                $destino = 'VITRINE';
                
                DB::table('estoque_lojas')->where('id', $estoque->id)->decrement('quantidade', $qtd);
                DB::table('estoque_lojas')->where('id', $estoque->id)->increment('quantidade_vitrine', $qtd);
                
                // Baixa inteligente dos lotes do depósito (FIFO)
                $this->baixar_lotes_inteligente($lojaId, $prodId, $qtd);
            }
            else if ($acao === 'transf_vit_dep') {
                // 🔒 VALIDAÇÃO DE SALDO DA VITRINE (CORREÇÃO DO BUG)
                if ($estoque->quantidade_vitrine < $qtd) {
                    throw new \Exception("Saldo insuficiente na Vitrine. Disponível: " . number_format($estoque->quantidade_vitrine, 3));
                }

                $tipoOperacao = 'TRANSFERENCIA';
                $origem = 'VITRINE';
                $destino = 'DEPOSITO';
                
                DB::table('estoque_lojas')->where('id', $estoque->id)->decrement('quantidade_vitrine', $qtd);
                DB::table('estoque_lojas')->where('id', $estoque->id)->increment('quantidade', $qtd);
                
                // Nota: Vitrine geralmente não rastreia lote na volta, assume-se retorno ao saldo geral.
            }
            else if ($acao === 'transf_entre_lojas') {
                if (!$request->loja_destino_id) throw new \Exception("Selecione a loja de destino.");
                
                $tipoOperacao = 'TRANSFERENCIA';
                $origem = DB::table('lojas')->where('id', $lojaId)->value('nome_fantasia');
                $destino = DB::table('lojas')->where('id', $request->loja_destino_id)->value('nome_fantasia');

                // Baixa Origem
                DB::table('estoque_lojas')->where('id', $estoque->id)->decrement('quantidade', $qtd);
                DB::table('estoque_lotes')->where('id', $loteIdManual)->decrement('quantidade_atual', $qtd);

                // Entrada Destino
                DB::table('estoque_lojas')->updateOrInsert(['loja_id' => $request->loja_destino_id, 'produto_id' => $prodId], ['updated_at' => now()]);
                DB::table('estoque_lojas')->where('loja_id', $request->loja_destino_id)->where('produto_id', $prodId)->increment('quantidade', $qtd);
                
                // Replica Lote na Loja Destino
                $loteOrigem = DB::table('estoque_lotes')->where('id', $loteIdManual)->first();
                DB::table('estoque_lotes')->insert([
                    'loja_id' => $request->loja_destino_id,
                    'produto_id' => $prodId,
                    'fornecedor_id' => $loteOrigem->fornecedor_id,
                    'quantidade_inicial' => $qtd,
                    'quantidade_atual' => $qtd,
                    'preco_custo' => $loteOrigem->preco_custo,
                    'validade' => $loteOrigem->validade,
                    'numero_lote' => $loteOrigem->numero_lote,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // --- 3. PERDAS ---
            else if ($acao === 'perda_deposito') {
                $tipoOperacao = 'PERDA';
                $origem = 'DEPOSITO';
                $destino = 'LIXO';
                
                if ($estoque->quantidade < $qtd) throw new \Exception("Saldo insuficiente no Depósito.");

                DB::table('estoque_lojas')->where('id', $estoque->id)->decrement('quantidade', $qtd); 

                if ($loteIdManual) {
                    DB::table('estoque_lotes')->where('id', $loteIdManual)->decrement('quantidade_atual', $qtd);
                } else {
                    $this->baixar_lotes_inteligente($lojaId, $prodId, $qtd);
                }
            }
            else if ($acao === 'perda_vitrine') {
                if ($estoque->quantidade_vitrine < $qtd) throw new \Exception("Saldo insuficiente na Vitrine.");
                
                $tipoOperacao = 'PERDA';
                $origem = 'VITRINE';
                $destino = 'LIXO';
                
                DB::table('estoque_lojas')->where('id', $estoque->id)->decrement('quantidade_vitrine', $qtd);
                // Baixa genérica de lotes pois saiu da empresa
                $this->baixar_lotes_inteligente($lojaId, $prodId, $qtd);
            }

            // REGISTRA NO HISTÓRICO
            DB::table('historico_movimentacoes')->insert([
                'loja_id' => $lojaId,
                'produto_id' => $prodId,
                'tipo_operacao' => $tipoOperacao,
                'origem' => $origem,
                'destino' => $destino,
                'quantidade' => $qtd,
                'custo_momento' => $estoque->preco_custo,
                'motivo' => $motivo . ($loteIdManual ? " (Lote ID: #$loteIdManual)" : ""),
                'created_at' => now()
            ]);

            DB::commit();
            return response()->json(['status' => 'sucesso', 'mensagem' => 'Movimentação realizada com sucesso!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista histórico de movimentações com filtros
     */
    public function listar_historico(Request $request)
    {
        $lojaId = $request->query('loja_id');
        $produtoId = $request->query('produto_id');
        $tipo = $request->query('tipo'); 
        $dataInicio = $request->query('data_inicio');
        $dataFim = $request->query('data_fim');

        $query = DB::table('historico_movimentacoes')
            ->join('produtos', 'historico_movimentacoes.produto_id', '=', 'produtos.id')
            ->where('historico_movimentacoes.loja_id', $lojaId)
            ->select('historico_movimentacoes.*', 'produtos.nome as produto_nome', 'produtos.unidade_medida')
            ->orderBy('historico_movimentacoes.created_at', 'desc');

        if ($produtoId) $query->where('historico_movimentacoes.produto_id', $produtoId);
        if ($tipo) $query->where('historico_movimentacoes.tipo_operacao', $tipo);
        if ($dataInicio) $query->whereDate('historico_movimentacoes.created_at', '>=', $dataInicio);
        if ($dataFim) $query->whereDate('historico_movimentacoes.created_at', '<=', $dataFim);

        return response()->json($query->get());
    }

    /**
     * Consome os lotes do produto priorizando os que possuem menor quantidade (sua regra)
     */
    private function baixar_lotes_inteligente($loja_id, $produto_id, $quantidade_para_baixar)
    {
        $lotes = DB::table('estoque_lotes')
            ->where('loja_id', $loja_id)
            ->where('produto_id', $produto_id)
            ->where('quantidade_atual', '>', 0)
            ->orderBy('quantidade_atual', 'asc') 
            ->get();

        $restante = $quantidade_para_baixar;
        foreach ($lotes as $lote) {
            if ($restante <= 0) break;
            $baixa = min($lote->quantidade_atual, $restante);
            DB::table('estoque_lotes')->where('id', $lote->id)->decrement('quantidade_atual', $baixa);
            $restante -= $baixa;
        }
        return $restante;
    }

    /**
     * Lista lotes disponíveis para seleção manual no frontend
     */
    public function listar_lotes_produto(Request $request)
    {
        $request->validate(['loja_id' => 'required', 'produto_id' => 'required']);

        $lotes = DB::table('estoque_lotes')
            ->leftJoin('fornecedores', 'estoque_lotes.fornecedor_id', '=', 'fornecedores.id')
            ->where('estoque_lotes.loja_id', $request->loja_id)
            ->where('estoque_lotes.produto_id', $request->produto_id)
            ->where('estoque_lotes.quantidade_atual', '>', 0)
            ->select(
                'estoque_lotes.id',
                'estoque_lotes.quantidade_atual',
                'estoque_lotes.preco_custo',
                'estoque_lotes.validade',
                'estoque_lotes.numero_lote',
                'fornecedores.nome_fantasia as fornecedor'
            )
            ->orderBy('estoque_lotes.quantidade_atual', 'asc') 
            ->get();

        return response()->json($lotes);
    }
}