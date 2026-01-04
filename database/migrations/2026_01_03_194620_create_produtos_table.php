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
            
            // Vínculo com a empresa (Rede)
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');

            // Classificação
            $table->enum('tipo_item', ['REVENDA', 'INTERNO', 'INSUMOS'])->default('REVENDA');
            $table->string('nome');
            $table->string('codigo_barras')->nullable();
            $table->string('unidade_medida', 5)->default('UN');
            
            $table->string('grupo_familia')->nullable();
            $table->string('categoria')->nullable();
            
            // Preços de Referência (Capa / Sugerido)
            // O preço real de venda fica na tabela estoque_lojas
            $table->decimal('preco_custo', 10, 2)->default(0);
            $table->decimal('preco_venda', 10, 2)->default(0);
            
            // OBS: Removemos estoque_atual e estoque_minimo daqui
            // pois agora eles são específicos por filial.
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produtos');
    }
};