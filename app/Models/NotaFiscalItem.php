<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaFiscalItem extends Model
{
    use HasFactory;
    protected $table = 'nota_fiscal_itens';

    protected $fillable = [
        'nota_fiscal_id', 'produto_id', 'nome_produto_xml',
        'codigo_produto_xml', 'ean_comercial', 'unidade_comercial',
        'ncm', 'cfop', 'quantidade', 'valor_unitario', 'valor_total'
    ];

    /**
     * RELAÇÃO QUE ESTAVA FALTANDO:
     * Retorna a nota fiscal a qual este item pertence.
     */
    public function notaFiscal()
    {
        return $this->belongsTo(NotaFiscal::class, 'nota_fiscal_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}