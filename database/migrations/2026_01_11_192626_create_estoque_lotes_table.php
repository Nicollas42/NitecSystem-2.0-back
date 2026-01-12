<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estoque_lotes', function (Blueprint $table) {
            $table->id();
            
            // Vínculos com as tabelas existentes
            $table->foreignId('loja_id')->constrained('lojas')->onDelete('cascade');
            $table->foreignId('produto_id')->constrained('produtos')->onDelete('cascade');
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores');
            
            // Permite rastrear de qual nota veio este lote
            $table->foreignId('nota_fiscal_id')->nullable()->constrained('notas_fiscais')->onDelete('set null');
            
            // Controle de Quantidades
            $table->decimal('quantidade_inicial', 10, 3); // O que entrou na nota
            $table->decimal('quantidade_atual', 10, 3);   // O que ainda resta (saldo do lote)
            
            // Dados financeiros e validade
            $table->decimal('preco_custo', 10, 2); 
            $table->date('validade')->nullable(); 
            
            // Identificador opcional (pode ser o lote do fabricante ou "LOTE-DATA")
            $table->string('numero_lote')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_lotes');
    }
};