<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->enum('tipo_energia', ['ELETRICO', 'GAS', 'MANUAL']);
            
            // Consumo
            $table->decimal('potencia_watts', 10, 2)->default(0);
            $table->decimal('consumo_gas_kg_h', 10, 3)->default(0);
            
            // Depreciação / Financeiro
            $table->decimal('valor_aquisicao', 10, 2)->default(0);
            $table->decimal('valor_residual', 10, 2)->default(0);
            $table->integer('vida_util_anos')->default(10);
            $table->decimal('depreciacao_hora', 10, 4)->default(0);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipamentos');
    }
};
