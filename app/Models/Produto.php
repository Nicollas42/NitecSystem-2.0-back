<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    use HasFactory;

    protected $table = 'produtos';

    // AQUI ESTÁ A CORREÇÃO: Adicionamos todos os campos novos
    protected $fillable = [
        'empresa_id',
        'nome',
        'codigo_barras',
        'unidade_medida',
        'tipo_item',
        'categoria',
        'grupo_familia',
        'preco_custo',
        'preco_venda', // Preço Base (Capa)
        // Note que estoque_atual não está aqui pois ele é gerenciado na outra tabela (estoque_lojas)
        // mas se sua migration antiga ainda tiver essa coluna, pode deixar aqui pra evitar erro.
        'estoque_atual', 
        'estoque_minimo'
    ];
}