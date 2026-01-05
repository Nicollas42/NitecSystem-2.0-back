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
        Schema::table('equipamentos', function (Blueprint $table) {
            // Adiciona a coluna loja_id logo após o id
            // unsignedBigInteger é o padrão para chaves estrangeiras no Laravel
            $table->unsignedBigInteger('loja_id')->after('id')->nullable();

            // Opcional: Cria a chave estrangeira (se a tabela 'lojas' existir)
            // Isso garante integridade (não deixa criar equipamento p/ loja inexistente)
            $table->foreign('loja_id')->references('id')->on('lojas')->onDelete('cascade');
            
            // Cria um índice para deixar a busca por loja rápida
            $table->index('loja_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipamentos', function (Blueprint $table) {
            // Remove a chave estrangeira primeiro (se tiver criado)
            $table->dropForeign(['loja_id']);
            // Remove a coluna
            $table->dropColumn('loja_id');
        });
    }
};