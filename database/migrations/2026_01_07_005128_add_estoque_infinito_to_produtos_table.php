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
        Schema::table('produtos', function (Blueprint $table) {
            $table->boolean('estoque_infinito')->default(false)->after('unidade_medida');
        });
    }

    public function down() 
    {
        Schema::table('produtos', function (Blueprint $table) {
            $table->dropColumn('estoque_infinito');
        });
    }
};
