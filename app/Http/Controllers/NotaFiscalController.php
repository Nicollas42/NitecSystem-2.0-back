<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\NotaFiscal;
use App\Models\NotaFiscalItem;
use App\Models\Fornecedor;
use App\Models\Produto;

class NotaFiscalController extends Controller
{
    /**
     * Passo 1: Recebe o XML, lê os dados e retorna para conferência
     */
    public function uploadXml(Request $request)
    {
        $request->validate([
            'arquivo_xml' => 'required|file', 
            'loja_id' => 'required'
        ]);

        $extension = $request->file('arquivo_xml')->getClientOriginalExtension();
        if (!in_array(strtolower($extension), ['xml', 'txt'])) {
            return response()->json(['mensagem' => 'O arquivo deve ser um XML ou TXT contendo XML.'], 422);
        }

        $user = Auth::user();
        $file = $request->file('arquivo_xml');
        $xmlContent = trim(file_get_contents($file->getRealPath()));

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent, "SimpleXMLElement", LIBXML_NOCDATA);
        
        if ($xml === false) {
            return response()->json(['mensagem' => 'Arquivo inválido. Não é um XML válido.'], 400);
        }

        $json = json_encode($xml);
        $array = json_decode($json, true);

        $infNFe = null;
        if (isset($array['infNFe'])) {
            $infNFe = $array['infNFe'];
        } elseif (isset($array['NFe']['infNFe'])) {
            $infNFe = $array['NFe']['infNFe'];
        }

        if (!$infNFe) {
            return response()->json(['mensagem' => 'Estrutura de NFe não encontrada.'], 400);
        }

        try {
            DB::beginTransaction();

            $chaveFull = $infNFe['@attributes']['Id'] ?? '';
            $chaveAcesso = str_replace('NFe', '', $chaveFull);

            $notaExistente = NotaFiscal::where('chave_acesso', $chaveAcesso)->first();

            if ($notaExistente) {
                if ($notaExistente->status === 'IMPORTADA') {
                    return response()->json(['mensagem' => 'Esta Nota Fiscal já foi importada.'], 409);
                }
                $notaExistente->itens()->delete();
                $notaExistente->delete();
            }

            // Fornecedor
            $emit = $infNFe['emit'];
            $cnpj = $emit['CNPJ'] ?? '';
            $nome = $emit['xNome'] ?? 'Desconhecido';

            $fornecedor = Fornecedor::updateOrCreate(
                ['cnpj' => $cnpj, 'empresa_id' => $user->empresa_id], 
                [
                    'nome_fantasia' => $emit['xFant'] ?? $nome,
                    'razao_social' => $nome,
                    'telefone' => $emit['enderEmit']['fone'] ?? null,
                    'vendedor_nome' => 'Importado via XML'
                ]
            );

            // Cabeçalho da Nota
            $ide = $infNFe['ide'];
            $dataEmissaoRaw = $ide['dhEmi'] ?? $ide['dEmi'] ?? now();
            $dataEmissao = date('Y-m-d H:i:s', strtotime(substr($dataEmissaoRaw, 0, 19)));
            $path = $file->store('xmls', 'public');

            $nota = NotaFiscal::create([
                'empresa_id' => $user->empresa_id,
                'user_id'    => $user->id,
                'loja_id'    => $request->loja_id,
                'fornecedor_id' => $fornecedor->id,
                'chave_acesso' => $chaveAcesso,
                'numero_nota' => $ide['nNF'],
                'serie' => $ide['serie'],
                'data_emissao' => $dataEmissao,
                'valor_total_produtos' => $infNFe['total']['ICMSTot']['vProd'],
                'valor_total_nota' => $infNFe['total']['ICMSTot']['vNF'],
                'xml_path' => $path,
                'status' => 'PENDENTE'
            ]);

            // Itens
            $detalhes = $infNFe['det'];
            if (isset($detalhes['prod'])) {
                $detalhes = [$detalhes];
            }

            foreach ($detalhes as $item) {
                $prod = $item['prod'];
                $ean = null;
                if (!empty($prod['cEAN']) && $prod['cEAN'] !== 'SEM GTIN') {
                    $ean = $prod['cEAN'];
                }

                $produtoId = null;
                if ($ean) {
                    $busca = Produto::where('empresa_id', $user->empresa_id)
                        ->where('codigo_barras', $ean)
                        ->first();
                    if ($busca) $produtoId = $busca->id;
                }

                NotaFiscalItem::create([
                    'nota_fiscal_id' => $nota->id,
                    'produto_id' => $produtoId,
                    'nome_produto_xml' => $prod['xProd'],
                    'codigo_produto_xml' => $prod['cProd'],
                    'ean_comercial' => $ean,
                    'unidade_comercial' => $prod['uCom'],
                    'ncm' => $prod['NCM'] ?? null,
                    'cfop' => $prod['CFOP'],
                    'quantidade' => $prod['qCom'],
                    'valor_unitario' => $prod['vUnCom'],
                    'valor_total' => $prod['vProd'],
                ]);
            }

            DB::commit();

            $nota->load('fornecedor'); 

            return response()->json([
                'mensagem' => 'Leitura realizada! Confira os vínculos.',
                'nota_id' => $nota->id,
                'cabecalho' => $nota,
                'itens' => $nota->itens()->with('produto')->get()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['mensagem' => 'Erro ao processar XML: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Passo 2: Confirmação e Entrada no Estoque com Controle de Lotes (CORRIGIDO)
     */
    public function confirmar_importacao(Request $request)
    {
        $user = Auth::user();
        
        // Se for manual, validamos o cabeçalho.
        if ($request->modo === 'manual') {
            $request->validate([
                'cabecalho.fornecedor_id' => 'required',
                'cabecalho.data' => 'required',
                'itens' => 'required|array|min:1'
            ]);
        } else {
            $request->validate(['itens' => 'required|array']);
        }
        
        try {
            DB::beginTransaction();

            $listaProcessar = [];

            // =============================================================
            // CENÁRIO A: NOTA MANUAL (Cria a Nota no Banco + Itens)
            // =============================================================
            if ($request->modo === 'manual') {
                
                // 1. Cria o registro da Nota Fiscal (Sem XML)
                $nota = NotaFiscal::create([
                    'empresa_id' => $user->empresa_id,
                    'user_id' => $user->id,
                    'loja_id' => $request->loja_id ?? $user->lojas->first()->id ?? null,
                    'fornecedor_id' => $request->cabecalho['fornecedor_id'],
                    'numero_nota' => $request->cabecalho['numero'] ?? 'S/N-' . time(),
                    'serie' => 'MAN', // <--- CORREÇÃO: "MANUAL" ESTAVA ESTOURANDO O LIMITE DE CARACTERES
                    'data_emissao' => $request->cabecalho['data'],
                    'valor_total_produtos' => 0,
                    'valor_total_nota' => 0,
                    'status' => 'IMPORTADA',
                    'chave_acesso' => 'MANUAL-' . time() . '-' . rand(100,999) 
                ]);

                $total = 0;

                // 2. Cria os Itens da Nota e prepara para o estoque
                foreach ($request->itens as $item) {
                    $subtotal = $item['quantidade'] * $item['valor_unitario'];
                    $total += $subtotal;

                    $prod = Produto::find($item['produto_destino_id']);

                    $notaItem = NotaFiscalItem::create([
                        'nota_fiscal_id' => $nota->id,
                        'produto_id' => $item['produto_destino_id'],
                        'nome_produto_xml' => $prod ? $prod->nome : 'ITEM MANUAL',
                        'quantidade' => $item['quantidade'],
                        'valor_unitario' => $item['valor_unitario'],
                        'valor_total' => $subtotal,
                        'codigo_produto_xml' => 'MANUAL',
                        'unidade_comercial' => $prod ? $prod->unidade_medida : 'UN'
                    ]);

                    $listaProcessar[] = [
                        'loja_id' => $nota->loja_id,
                        'produto_id' => $item['produto_destino_id'],
                        'quantidade' => $item['quantidade'],
                        'custo' => $item['valor_unitario'],
                        'nota_id' => $nota->id,
                        'fornecedor_id' => $nota->fornecedor_id,
                        'numero_nota' => $nota->numero_nota
                    ];
                }

                $nota->update(['valor_total_produtos' => $total, 'valor_total_nota' => $total]);

            } 
            // =============================================================
            // CENÁRIO B: IMPORTAÇÃO XML (Já existe no banco, só processa)
            // =============================================================
            else {
                foreach ($request->itens as $vinculo) {
                    $notaItem = NotaFiscalItem::with('notaFiscal')->findOrFail($vinculo['item_id']);
                    $nota = $notaItem->notaFiscal;

                    $notaItem->update(['produto_id' => $vinculo['produto_destino_id']]);

                    $listaProcessar[] = [
                        'loja_id' => $nota->loja_id,
                        'produto_id' => $vinculo['produto_destino_id'],
                        'quantidade' => $notaItem->quantidade,
                        'custo' => $notaItem->valor_unitario,
                        'nota_id' => $nota->id,
                        'fornecedor_id' => $nota->fornecedor_id,
                        'numero_nota' => $nota->numero_nota
                    ];

                    if ($nota->status !== 'IMPORTADA') {
                        $nota->update(['status' => 'IMPORTADA']);
                    }
                }
            }

            // =============================================================
            // PROCESSAMENTO DE ESTOQUE E LOTES (COMUM AOS DOIS)
            // =============================================================
            foreach ($listaProcessar as $dados) {
                
                // 1. Atualiza Saldo Geral
                DB::table('estoque_lojas')->updateOrInsert(
                    ['loja_id' => $dados['loja_id'], 'produto_id' => $dados['produto_id']],
                    [
                        'quantidade' => DB::raw("quantidade + " . $dados['quantidade']),
                        'preco_custo' => $dados['custo'],
                        'updated_at' => now()
                    ]
                );

                // 2. Cria Lote
                DB::table('estoque_lotes')->insert([
                    'loja_id' => $dados['loja_id'],
                    'produto_id' => $dados['produto_id'],
                    'fornecedor_id' => $dados['fornecedor_id'],
                    'nota_fiscal_id' => $dados['nota_id'],
                    'quantidade_inicial' => $dados['quantidade'],
                    'quantidade_atual' => $dados['quantidade'],
                    'preco_custo' => $dados['custo'],
                    'numero_lote' => 'NF-' . $dados['numero_nota'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // 3. Histórico
                DB::table('historico_movimentacoes')->insert([
                    'loja_id' => $dados['loja_id'],
                    'produto_id' => $dados['produto_id'],
                    'tipo_operacao' => 'ENTRADA',
                    'origem' => $request->modo === 'manual' ? 'Entrada Manual' : 'Importação XML',
                    'destino' => 'Estoque Loja',
                    'quantidade' => $dados['quantidade'],
                    'custo_momento' => $dados['custo'], 
                    'motivo' => 'NFe ' . $dados['numero_nota'],
                    'created_at' => now()
                ]);
            }

            DB::commit();
            return response()->json(['mensagem' => 'Entrada realizada com sucesso!']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['mensagem' => 'Erro: ' . $e->getMessage()], 500);
        }
    }
}