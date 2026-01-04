<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estoque_lojas', function (Blueprint $table) {
            // Adiciona a coluna vitrine logo após a quantidade (depósito)
            $table->decimal('quantidade_vitrine', 10, 3)->default(0)->after('quantidade');
        });
    }

    public function down(): void
    {
        Schema::table('estoque_lojas', function (Blueprint $table) {
            $table->dropColumn('quantidade_vitrine');
        });
    }
};