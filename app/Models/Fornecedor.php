<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fornecedor extends Model
{
    use HasFactory;

    // AVISAR AO LARAVEL O NOME CORRETO DA TABELA
    protected $table = 'fornecedores';

    // Se quiser liberar o mass assignment (opcional, mas recomendado para o create)
    protected $fillable = [
        'empresa_id',
        'nome_fantasia',
        'razao_social',
        'cnpj',
        'telefone',
        'vendedor_nome',
        'vendedor_telefone'
    ];
}