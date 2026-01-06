<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            // Código curto usado na balança (ex: 50, 102)
            // É único por empresa para não conflitar
            $table->integer('codigo_balanca')->nullable()->after('codigo_barras');
        });
    }

    public function down(): void
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('codigo_balanca');
        });
    }
};