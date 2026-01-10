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
        Schema::create('fornecedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas'); // Global para a rede
            $table->string('nome_fantasia');
            $table->string('razao_social')->nullable();
            $table->string('cnpj')->nullable();
            $table->string('telefone')->nullable();
            $table->string('vendedor_nome')->nullable(); // Nome do contato lá dentro
            $table->timestamps();
        });

        // Vamos alterar a tabela de histórico para suportar essa inteligência
        Schema::table('historico_movimentacoes', function (Blueprint $table) {
            $table->foreignId('fornecedor_id')->nullable()->after('produto_id')->constrained('fornecedores');
            $table->decimal('custo_momento', 10, 2)->nullable()->after('quantidade'); // Quanto pagou na época
        });
    }

    public function down()
    {
        Schema::table('historico_movimentacoes', function (Blueprint $table) {
            $table->dropForeign(['fornecedor_id']);
            $table->dropColumn(['fornecedor_id', 'custo_momento']);
        });
        Schema::dropIfExists('fornecedores');
    }
};
