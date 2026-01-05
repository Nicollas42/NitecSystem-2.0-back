<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipamentoController extends Controller
{
    // Lista equipamentos APENAS da loja solicitada
    public function index(Request $request)
    {
        $lojaId = $request->query('loja_id');

        $query = DB::table('equipamentos');

        if ($lojaId) {
            $query->where('loja_id', $lojaId);
        }

        $equipamentos = $query->orderBy('nome')->get();
        
        return response()->json($equipamentos);
    }

    // Salva vinculando à loja
    public function store(Request $request)
    {
        $request->validate([
            'nome' => 'required|string',
            'tipo_energia' => 'required|string',
            'loja_id' => 'required' // Obrigatório para saber de quem é a máquina
        ]);

        $id = DB::table('equipamentos')->insertGetId([
            'loja_id' => $request->loja_id,
            'nome' => $request->nome,
            'tipo_energia' => $request->tipo_energia,
            'potencia_watts' => $request->potencia_watts ?? 0,
            'consumo_gas_kg_h' => $request->consumo_gas_kg_h ?? 0,
            'valor_aquisicao' => $request->valor_aquisicao ?? 0,
            'valor_residual' => $request->valor_residual ?? 0,
            'vida_util_anos' => $request->vida_util_anos ?? 10,
            'depreciacao_hora' => $request->depreciacao_mensal ? ($request->depreciacao_mensal / 220) : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id, 'message' => 'Equipamento salvo!'], 201);
    }
}