<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Produto extends Model
{
    use HasFactory;

    /**
     * Tabela associada ao modelo.
     *
     * @var string
     */
    protected $table = 'produtos';

    /**
     * Os atributos que podem ser atribuídos em massa.
     *
     * @var array
     */
    public $incrementing = false;
    
    protected $fillable = [
        'id',
        'tipo_item',
        'nome',
        'grupo_familia',
        'categoria',
        'codigo_barras',
        'unidade_medida',
        'preco_custo',
        'preco_venda',
        'estoque_atual',
        'estoque_minimo'
    ];
}