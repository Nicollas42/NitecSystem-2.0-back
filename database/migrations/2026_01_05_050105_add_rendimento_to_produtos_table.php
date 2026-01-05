<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Rendimento da receita (Ex: 200 unidades, 5 kg)
            // Default 1 para evitar divisão por zero
            $table->decimal('rendimento', 10, 3)->default(1)->after('unidade_medida'); 
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('rendimento');
        });
    }
};