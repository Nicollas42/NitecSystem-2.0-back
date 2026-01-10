<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MovimentacaoController extends Controller
{
    public function registrar(Request $request)
    {
        $request->validate([
            'loja_id' => 'required',
            'produto_id' => 'required',
            'acao' => 'required', // entrada, transferencia, perda
            'quantidade' => 'required|numeric|min:0.001',
            'motivo' => 'required|string'
        ]);

        $lojaId = $request->loja_id;
        $prodId = $request->produto_id;
        $qtd = $request->quantidade;
        $motivo = $request->motivo;
        $acao = $request->acao;

        try {
            DB::beginTransaction();

            // Busca saldo atual para validar saídas
            $estoque = DB::table('estoque_lojas')
                ->where('loja_id', $lojaId)
                ->where('produto_id', $prodId)
                ->first();

            if (!$estoque) throw new \Exception("Produto não cadastrado no estoque desta loja.");

            $origem = '';
            $destino = '';
            $tipoOperacao = '';

            // --- LÓGICA 1: ENTRADA (Compra / Produção Extra) ---
            if ($acao === 'entrada_deposito') {
                $tipoOperacao = 'ENTRADA';
                $origem = 'EXTERNO';
                $destino = 'DEPOSITO';

                DB::table('estoque_lojas')->where('id', $estoque->id)->increment('quantidade', $qtd);
            }

            // --- LÓGICA 2: TRANSFERÊNCIA (Depósito <-> Vitrine) ---
            else if ($acao === 'transf_dep_vit') {
                // Depósito -> Vitrine
                if ($estoque->quantidade < $qtd) throw new \Exception("Saldo insuficiente no Depósito.");
                
                $tipoOperacao = 'TRANSFERENCIA';
                $origem = 'DEPOSITO';
                $destino = 'VITRINE';

                DB::table('estoque_lojas')->where('id', $estoque->id)->decrement('quantidade', $qtd);
                DB::table('estoque_lojas')->where('id', $estoque->id)->increment('quantidade_vitrine', $qtd);
            }
            else if ($acao === 'transf_vit_dep') {
                // Vitrine -> Depósito
                if ($estoque->quantidade_vitrine < $qtd) throw new \Exception("Saldo insuficiente na Vitrine.");

                $tipoOperacao = 'TRANSFERENCIA';
                $origem = 'VITRINE';
                $destino = 'DEPOSITO';

                DB::table('estoque_lojas')->where('id', $estoque->id)->decrement('quantidade_vitrine', $qtd);
                DB::table('estoque_lojas')->where('id', $estoque->id)->increment('quantidade', $qtd);
            }

            // --- LÓGICA 3: PERDA / QUEBRA ---
            else if ($acao === 'perda_deposito') {
                $tipoOperacao = 'PERDA';
                $origem = 'DEPOSITO';
                $destino = 'LIXO';
                DB::table('estoque_lojas')->where('id', $estoque->id)->decrement('quantidade', $qtd); // Pode ficar negativo ou trava? Aqui deixo ir, mas poderia travar.
            }
            else if ($acao === 'perda_vitrine') {
                $tipoOperacao = 'PERDA';
                $origem = 'VITRINE';
                $destino = 'LIXO';
                DB::table('estoque_lojas')->where('id', $estoque->id)->decrement('quantidade_vitrine', $qtd);
            }

            // --- REGISTRA NO HISTÓRICO ---
            DB::table('historico_movimentacoes')->insert([
                'loja_id' => $lojaId,
                'produto_id' => $prodId,
                'tipo_operacao' => $tipoOperacao,
                'origem' => $origem,
                'destino' => $destino,
                'quantidade' => $qtd,
                'motivo' => $motivo,
                'created_at' => now()
            ]);

            DB::commit();
            return response()->json(['status' => 'sucesso', 'mensagem' => 'Movimentação realizada com sucesso!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    public function listar_historico(Request $request)
    {
        $lojaId = $request->query('loja_id');
        $produtoId = $request->query('produto_id');
        $tipo = $request->query('tipo'); // ENTRADA, SAIDA, PERDA, TRANSFERENCIA
        $dataInicio = $request->query('data_inicio');
        $dataFim = $request->query('data_fim');

        $query = DB::table('historico_movimentacoes')
            ->join('produtos', 'historico_movimentacoes.produto_id', '=', 'produtos.id')
            ->where('historico_movimentacoes.loja_id', $lojaId)
            ->select(
                'historico_movimentacoes.*',
                'produtos.nome as produto_nome',
                'produtos.unidade_medida'
            )
            ->orderBy('historico_movimentacoes.created_at', 'desc');

        // Filtros
        if ($produtoId) {
            $query->where('historico_movimentacoes.produto_id', $produtoId);
        }
        if ($tipo) {
            $query->where('historico_movimentacoes.tipo_operacao', $tipo);
        }
        if ($dataInicio) {
            $query->whereDate('historico_movimentacoes.created_at', '>=', $dataInicio);
        }
        if ($dataFim) {
            $query->whereDate('historico_movimentacoes.created_at', '<=', $dataFim);
        }

        return response()->json($query->get());
    }
}