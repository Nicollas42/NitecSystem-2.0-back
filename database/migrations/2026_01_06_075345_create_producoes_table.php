<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loja_id')->constrained('lojas'); // Quem produziu
            $table->foreignId('produto_id')->constrained('produtos'); // O que produziu
            $table->decimal('quantidade_produzida', 10, 3); // Quanto produziu
            $table->decimal('custo_unitario_momento', 10, 2); // Custo na época
            $table->timestamp('created_at')->useCurrent(); // Data da produção
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producoes');
    }
};