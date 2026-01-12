<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaFiscal extends Model
{
    use HasFactory;
    protected $table = 'notas_fiscais';

    protected $fillable = [
        'empresa_id', 
        'user_id', // <--- ADICIONADO PARA RASTREABILIDADE
        'loja_id', 
        'fornecedor_id', 
        'chave_acesso',
        'numero_nota', 
        'serie', 
        'data_emissao', 
        'valor_total_produtos',
        'valor_total_nota', 
        'xml_path', 
        'status'
    ];
    
    public function itens()
    {
        return $this->hasMany(NotaFiscalItem::class, 'nota_fiscal_id');
    }

    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class, 'fornecedor_id');
    }

    // RELAÇÃO NOVA: Saber qual usuário importou
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}