<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabela que liga: Produto (Bolo) -> Insumos (Farinha, Ovo)
        Schema::create('ficha_tecnica_ingredientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produto_id'); // O Produto Pai (Bolo)
            $table->unsignedBigInteger('insumo_id');  // O Insumo (Farinha)
            $table->decimal('qtd_usada', 10, 4);      // Quanto usou (0.500 kg)
            
            $table->foreign('produto_id')->references('id')->on('produtos')->onDelete('cascade');
            $table->foreign('insumo_id')->references('id')->on('produtos');
        });

        // Tabela que liga: Produto (Bolo) -> Máquinas (Forno)
        Schema::create('ficha_tecnica_maquinas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produto_id');     // O Produto Pai
            $table->unsignedBigInteger('equipamento_id'); // A Máquina
            $table->integer('tempo_minutos');             // Tempo de uso
            
            $table->foreign('produto_id')->references('id')->on('produtos')->onDelete('cascade');
            $table->foreign('equipamento_id')->references('id')->on('equipamentos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ficha_tecnica_maquinas');
        Schema::dropIfExists('ficha_tecnica_ingredientes');
    }
};