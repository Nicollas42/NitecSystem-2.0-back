<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes_custos', function (Blueprint $table) {
            $table->integer('id')->primary(); // ID manual (sempre será 1)
            $table->decimal('custo_energia_kwh', 10, 4)->default(0.90);
            $table->decimal('custo_gas_kg', 10, 4)->default(8.50);
            $table->decimal('custo_agua_m3', 10, 4)->default(12.00);
            $table->decimal('custo_mao_obra_hora', 10, 4)->default(15.00);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes_custos');
    }
};
