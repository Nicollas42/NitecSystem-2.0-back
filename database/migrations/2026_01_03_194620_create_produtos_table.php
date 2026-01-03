<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produtos', function (Blueprint $table) {
            $table->id();
            // Futuramente você pode descomentar a linha abaixo para vincular produto à empresa
            // $table->foreignId('empresa_id')->constrained('empresas');

            $table->enum('tipo_item', ['REVENDA', 'INTERNO', 'INSUMOS'])->default('REVENDA');
            $table->string('nome');
            $table->string('codigo_barras')->nullable();
            $table->string('unidade_medida', 5)->default('UN');
            
            // Organização
            $table->string('grupo_familia')->nullable();
            $table->string('categoria')->nullable();
            
            // Valores e Estoque (Decimal com precisão para quantidades quebradas e valores)
            $table->decimal('preco_custo', 10, 2)->default(0);
            $table->decimal('preco_venda', 10, 2)->default(0);
            $table->decimal('estoque_atual', 10, 3)->default(0);
            $table->decimal('estoque_minimo', 10, 3)->default(5);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};
