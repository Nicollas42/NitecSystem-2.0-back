<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            // Adiciona logo após o nome do vendedor
            $table->string('vendedor_telefone')->nullable()->after('vendedor_nome');
        });
    }

    public function down()
    {
        Schema::table('fornecedores', function (Blueprint $table) {
            $table->dropColumn('vendedor_telefone');
        });
    }
};
