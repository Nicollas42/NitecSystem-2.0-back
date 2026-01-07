<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Produto;

class ProducaoController extends Controller
{
    /**
     * REGISTRAR PRODUÇÃO E MOVIMENTAR ESTOQUE
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

            // 1. Busca dados do Produto Principal e seu Rendimento Base
            $produto = DB::table('produtos')->where('id', $prodId)->first();
            $rendimentoBase = (float) $produto->rendimento;

            if ($rendimentoBase <= 0) {
                throw new \Exception("O rendimento base deste produto é inválido (Zero). Revise a Ficha Técnica.");
            }

            // 2. Calcula o Fator de Proporção (Ex: Quero 100kg, Base é 50kg -> Fator = 2)
            $fator = $qtdProduzir / $rendimentoBase;

            // 3. Busca os Ingredientes da Ficha Técnica
            $ingredientes = DB::table('ficha_tecnica_ingredientes')
                ->where('produto_id', $prodId)
                ->get();

            if ($ingredientes->isEmpty()) {
                throw new \Exception("Este produto não possui ingredientes cadastrados na Ficha Técnica.");
            }

            // 4. DEBITAR INGREDIENTES (Baixa de Estoque)
            foreach ($ingredientes as $ing) {
                // Se for item de Estoque Infinito (Água, Energia), PULA a baixa ou apenas registra negativo sem travar
                // Mas a lógica principal é: NÃO TRAVAR
                
                // Busca se é infinito
                $produtoInfo = DB::table('produtos')->where('id', $ing->insumo_id)->first();
                
                $qtdNecessaria = $ing->qtd_usada * $fator;

                // LÓGICA DA OPÇÃO B:
                if ($produtoInfo->estoque_infinito) {
                    // Se é infinito, a gente até pode debitar (vai ficar negativo, tipo -1000 Litros), 
                    // mas NÃO VAMOS BLOQUEAR se faltar.
                    // Ou melhor: nem fazemos a validação de saldo insuficiente.
                } else {
                    // LÓGICA PADRÃO (Trava se faltar)
                    $estoqueAtual = DB::table('estoque_lojas')
                        ->where('loja_id', $lojaId)
                        ->where('produto_id', $ing->insumo_id)
                        ->value('quantidade');

                    if ($estoqueAtual < $qtdNecessaria) {
                        throw new \Exception("Estoque insuficiente de {$ing->nome}. Necessário: {$qtdNecessaria}, Atual: {$estoqueAtual}");
                    }
                }

                // Decrementa do estoque da loja
                DB::table('estoque_lojas')->updateOrInsert(
                    ['loja_id' => $lojaId, 'produto_id' => $ing->insumo_id],
                    ['updated_at' => now()]
                ); // Garante que a linha existe antes de decrementar (embora insumo deva existir)

                DB::table('estoque_lojas')
                    ->where('loja_id', $lojaId)
                    ->where('produto_id', $ing->insumo_id)
                    ->decrement('quantidade', $qtdNecessaria);
            }

            // 5. CREDITAR PRODUTO ACABADO (Entrada de Estoque)
            // Primeiro busca o custo atual para salvar no histórico
            $dadosEstoque = DB::table('estoque_lojas')
                ->where('loja_id', $lojaId)
                ->where('produto_id', $prodId)
                ->first();
            
            $custoAtual = $dadosEstoque ? $dadosEstoque->preco_custo : 0;

            // Incrementa o estoque do produto final
            DB::table('estoque_lojas')->updateOrInsert(
                ['loja_id' => $lojaId, 'produto_id' => $prodId],
                ['updated_at' => now()]
            );
            
            DB::table('estoque_lojas')
                ->where('loja_id', $lojaId)
                ->where('produto_id', $prodId)
                ->increment('quantidade', $qtdProduzir);

            // 6. Registrar no Histórico
            DB::table('producoes')->insert([
                'loja_id' => $lojaId,
                'produto_id' => $prodId,
                'quantidade_produzida' => $qtdProduzir,
                'custo_unitario_momento' => $custoAtual,
                'created_at' => now()
            ]);

            DB::commit();
            return response()->json(['status' => 'sucesso', 'mensagem' => 'Produção registrada! Estoque atualizado.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'erro', 'mensagem' => $e->getMessage()], 500);
        }
    }
}