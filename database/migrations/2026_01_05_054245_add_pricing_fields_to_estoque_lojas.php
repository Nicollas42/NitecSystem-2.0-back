<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estoque_lojas', function (Blueprint $table) {
            // Margem de lucro (ex: 100.00 %)
            $table->decimal('margem_lucro', 10, 2)->default(0)->after('preco_venda');
            // Imposto na venda (ex: 4.50 %)
            $table->decimal('imposto_venda', 10, 2)->default(0)->after('margem_lucro');
        });
    }

    public function down(): void
    {
        Schema::table('estoque_lojas', function (Blueprint $table) {
            $table->dropColumn(['margem_lucro', 'imposto_venda']);
        });
    }
};