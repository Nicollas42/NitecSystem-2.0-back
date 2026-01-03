<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EquipamentoController extends Controller
{
    // Lista todos os equipamentos
    public function index()
    {
        // Pega do banco (tabela 'equipamentos' que criamos no SQL anterior)
        $equipamentos = DB::table('equipamentos')->orderBy('nome')->get();
        return response()->json($equipamentos);
    }

    // Salva um novo equipamento
    public function store(Request $request)
    {
        $request->validate([
            'nome' => 'required|string',
            'tipo_energia' => 'required|string',
        ]);

        $id = DB::table('equipamentos')->insertGetId([
            'nome' => $request->nome,
            'tipo_energia' => $request->tipo_energia,
            'potencia_watts' => $request->potencia_watts ?? 0,
            'consumo_gas_kg_h' => $request->consumo_gas_kg_h ?? 0,
            'valor_aquisicao' => $request->valor_aquisicao ?? 0,
            'valor_residual' => $request->valor_residual ?? 0,
            'vida_util_anos' => $request->vida_util_anos ?? 10,
            // Calcula depreciação hora: (Valor - Residual) / Anos / 12 meses / 220 horas
            'depreciacao_hora' => $request->depreciacao_mensal ? ($request->depreciacao_mensal / 220) : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id, 'message' => 'Equipamento salvo!'], 201);
    }
}