<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Produto;

class ProducaoController extends Controller
{
    /**
     * HISTÓRICO: Lista as produções realizadas (CABEÇALHO)
     */
    public function listar_historico(Request $request)
    {
        $lojaId = $request->query('loja_id');
        
        $historico = DB::table('producoes')
            ->join('produtos', 'producoes.produto_id', '=', 'produtos.id')
            ->where('producoes.loja_id', $lojaId)
            ->select(
                'producoes.id',
                'producoes.created_at',
                'producoes.quantidade_produzida as quantidade', // Alias para o front
                'produtos.nome as produto_nome',
                'produtos.unidade_medida'
            )
            ->orderBy('producoes.created_at', 'desc')
            ->limit(50) // Limita para não pesar
            ->get();

        return response()->json($historico);
    }

    /**
     * REGISTRAR PRODUÇÃO E MOVIMENTAR ESTOQUE COMPLETO
     */
    public function registrar(Request $request)
    {
        $request->validate([
            'loja_id' => 'required',
            'produto_id' => 'required',
            'quantidade' => 'required|numeric|min:0.001'
        ]);

        $lojaId = $request->loja_id;
        $prodId = $request->produto_id;
        $qtdProduzir = $request->quantidade;

        try {
            DB::beginTransaction();

            // 1. Busca dados do Produto Principal
            $produto = DB::table('produtos')->where('id', $prodId)->first();
            $rendimentoBase = (float) $produto->rendimento;

            if ($rendimentoBase <= 0) {
                throw new \Exception("O rendimento base é inválido (Zero). Revise a Ficha Técnica.");
            }

            // 2. Calcula Fator
            $fator = $qtdProduzir / $rendimentoBase;

            // 3. Busca Ingredientes
            $ingredientes = DB::table('ficha_tecnica_ingredientes')
                ->where('produto_id', $prodId)
                ->get();

            if ($ingredientes->isEmpty()) {
                throw new \Exception("Este produto não possui ingredientes na Ficha Técnica.");
            }

            // ---------------------------------------------------------
            // 4. REGISTRO DO LOTE (TABELA PRODUCOES)
            // ---------------------------------------------------------
            // Buscamos o custo atual para salvar o histórico financeiro
            $dadosEstoqueFinal = DB::table('estoque_lojas')
                ->where('loja_id', $lojaId)->where('produto_id', $prodId)->first();
            $custoMomentaneo = $dadosEstoqueFinal ? $dadosEstoqueFinal->preco_custo : 0;

            $producaoId = DB::table('producoes')->insertGetId([
                'loja_id' => $lojaId,
                'produto_id' => $prodId,
                'quantidade_produzida' => $qtdProduzir,
                'custo_unitario_momento' => $custoMomentaneo,
                'created_at' => now()
            ]);

            // ---------------------------------------------------------
            // 5. BAIXA DOS INSUMOS (SAÍDA DE ESTOQUE)
            // ---------------------------------------------------------
            foreach ($ingredientes as $ing) {
                $produtoIngrediente = DB::table('produtos')->where('id', $ing->insumo_id)->first();
                $qtdNecessaria = $ing->qtd_usada * $fator;

                // Verifica se é Estoque Infinito (Água, Gás)
                if (!$produtoIngrediente->estoque_infinito) {
                    $estoqueAtual = DB::table('estoque_lojas')
                        ->where('loja_id', $lojaId)
                        ->where('produto_id', $ing->insumo_id)
                        ->value('quantidade');

                    // Validação de Saldo
                    if ($estoqueAtual < $qtdNecessaria) {
                        throw new \Exception("Estoque insuficiente de {$produtoIngrediente->nome}. Necessário: " . number_format($qtdNecessaria, 3));
                    }

                    // A) DEBITA DO ESTOQUE GERAL
                    DB::table('estoque_lojas')
                        ->where('loja_id', $lojaId)
                        ->where('produto_id', $ing->insumo_id)
                        ->decrement('quantidade', $qtdNecessaria);

                    // B) NOVO: DEBITA DOS LOTES ESPECÍFICOS (MENOR QUANTIDADE PRIMEIRO)
                    $this->consumir_lotes_insumo($lojaId, $ing->insumo_id, $qtdNecessaria);
                }

                // REGISTRA NO HISTÓRICO DE MOVIMENTAÇÃO (SAÍDA)
                DB::table('historico_movimentacoes')->insert([
                    'loja_id' => $lojaId,
                    'produto_id' => $ing->insumo_id,
                    'tipo_operacao' => 'SAIDA',
                    'origem' => 'DEPOSITO',
                    'destino' => 'PRODUCAO',
                    'quantidade' => $qtdNecessaria,
                    'motivo' => "Insumo p/ Produção #{$producaoId} ({$produto->nome})",
                    'created_at' => now()
                ]);
            }

            // ---------------------------------------------------------
            // 6. ENTRADA DO PRODUTO ACABADO (ENTRADA DE ESTOQUE)
            // ---------------------------------------------------------
            // Garante que o registro de estoque existe na loja
            DB::table('estoque_lojas')->updateOrInsert(
                ['loja_id' => $lojaId, 'produto_id' => $prodId],
                ['updated_at' => now()]
            );
            
            // Incrementa o saldo geral
            DB::table('estoque_lojas')
                ->where('loja_id', $lojaId)
                ->where('produto_id', $prodId)
                ->increment('quantidade', $qtdProduzir);

            // NOVO: REGISTRA O LOTE DA PRODUÇÃO FINALIZADA
            DB::table('estoque_lotes')->insert([
                'loja_id'            => $lojaId,
                'produto_id'         => $prodId,
                'fornecedor_id'      => $produto->fornecedor_id ?? null,
                'nota_fiscal_id'     => null,
                'quantidade_inicial' => $qtdProduzir,
                'quantidade_atual'   => $qtdProduzir,
                'preco_custo'        => $custoMomentaneo,
                'numero_lote'        => 'PROD-' . $producaoId,
                'created_at'         => now(),
                'updated_at'         => now()
            ]);

            // REGISTRA NO HISTÓRICO DE MOVIMENTAÇÃO (ENTRADA)
            DB::table('historico_movimentacoes')->insert([
                'loja_id' => $lojaId,
                'produto_id' => $prodId,
                'tipo_operacao' => 'ENTRADA',
                'origem' => 'PRODUCAO',
                'destino' => 'DEPOSITO',
                'quantidade' => $qtdProduzir,
                'motivo' => "Produção Finalizada (Lote #{$producaoId})",
                'created_at' => now()
            ]);

            DB::commit();
            return response()->json(['status' => 'sucesso', 'mensagem' => 'Produção registrada com sucesso!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }

    /**
     * Consome os lotes do insumo priorizando os que possuem menor quantidade atual.
     * @param int $loja_id
     * @param int $produto_id
     * @param float $quantidade_necessaria
     */
    private function consumir_lotes_insumo($loja_id, $produto_id, $quantidade_necessaria)
    {
        // Busca lotes com saldo positivo, ordenando pela MENOR quantidade
        $lotes = DB::table('estoque_lotes')
            ->where('loja_id', $loja_id)
            ->where('produto_id', $produto_id)
            ->where('quantidade_atual', '>', 0)
            ->orderBy('quantidade_atual', 'asc')
            ->get();

        $restante = $quantidade_necessaria;

        foreach ($lotes as $lote) {
            if ($restante <= 0) break;

            $valor_baixa = min($lote->quantidade_atual, $restante);

            DB::table('estoque_lotes')
                ->where('id', $lote->id)
                ->decrement('quantidade_atual', $valor_baixa);

            $restante -= $valor_baixa;
        }
    }
}