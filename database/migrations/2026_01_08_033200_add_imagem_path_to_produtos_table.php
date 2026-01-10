<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('produtos', function (Blueprint $table) {
        // Caminho relativo da imagem (ex: produtos/foto123.jpg)
        $table->string('imagem_path')->nullable()->after('nome');
    });
}

public function down()
{
    Schema::table('produtos', function (Blueprint $table) {
        $table->dropColumn('imagem_path');
    });
}
};
