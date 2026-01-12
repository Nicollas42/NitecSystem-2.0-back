<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primeiro garantimos que a coluna existe e tem o tipo correto
        if (!Schema::hasColumn('notas_fiscais', 'user_id')) {
            Schema::table('notas_fiscais', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->after('empresa_id');
            });
        }

        // Depois aplicamos a chave estrangeira em um comando separado
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('usuarios')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('notas_fiscais', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
