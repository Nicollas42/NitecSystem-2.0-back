<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoque_lojas', function (Blueprint $table) {
            $table->id();
            
            // Vínculos fundamentais
            $table->foreignId('loja_id')->constrained('lojas')->onDelete('cascade');
            $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
            
            // DADOS ESPECÍFICOS DA LOJA (Onde a mágica acontece)
            $table->decimal('quantidade', 10, 3)->default(0);      // Quanto tem na prateleira
            $table->decimal('estoque_minimo', 10, 3)->default(5);  // Alerta de reposição (Nome corrigido!)
            
            // PREÇOS LOCAIS (Permite preços diferentes entre Matriz e Filial)
            $table->decimal('preco_venda', 10, 2)->nullable(); 
            $table->decimal('preco_custo', 10, 2)->nullable(); 
            
            // Garante que não duplique o mesmo produto na mesma loja
            $table->unique(['loja_id', 'produto_id']);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_lojas');
    }
};