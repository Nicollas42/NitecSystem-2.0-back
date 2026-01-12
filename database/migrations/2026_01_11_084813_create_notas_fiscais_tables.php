<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. CABEÇALHO DA NOTA
        Schema::create('notas_fiscais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('loja_id'); // Para qual estoque vai entrar
            $table->unsignedBigInteger('fornecedor_id')->nullable(); // Pode ser nulo até identificarmos
            
            $table->string('chave_acesso', 44)->unique();
            $table->string('numero_nota', 20);
            $table->string('serie', 5)->nullable();
            $table->dateTime('data_emissao');
            
            $table->decimal('valor_total_produtos', 10, 2);
            $table->decimal('valor_total_nota', 10, 2);
            
            // Caminho do arquivo XML salvo no storage (para auditoria)
            $table->string('xml_path')->nullable();
            
            // Status: PENDENTE (xml lido, mas não confirmado) / IMPORTADA (estoque atualizado)
            $table->enum('status', ['PENDENTE', 'IMPORTADA', 'CANCELADA'])->default('PENDENTE');
            
            $table->timestamps();

            // Chaves estrangeiras
            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('loja_id')->references('id')->on('lojas');
            $table->foreign('fornecedor_id')->references('id')->on('fornecedores');
        });

        // 2. ITENS DA NOTA
        Schema::create('nota_fiscal_itens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nota_fiscal_id');
            $table->unsignedBigInteger('produto_id')->nullable(); // O nosso produto vinculado
            
            $table->string('nome_produto_xml'); // Nome que veio no XML (Ex: "Far Trigo 1kg")
            $table->string('codigo_produto_xml'); // Código interno do fornecedor
            $table->string('ean_comercial', 14)->nullable(); // Código de barras
            $table->string('unidade_comercial', 10); // UN, CX, KG
            $table->string('ncm', 10)->nullable();
            $table->string('cfop', 5)->nullable();
            
            // 18 dígitos total, 4 decimais -> Permite até 99 trilhões (99.999.999.999.999,9999)
            $table->decimal('quantidade', 18, 4);
            $table->decimal('valor_unitario', 18, 4);
            $table->decimal('valor_total', 18, 2);
            
            $table->timestamps();

            $table->foreign('nota_fiscal_id')->references('id')->on('notas_fiscais')->onDelete('cascade');
            $table->foreign('produto_id')->references('id')->on('produtos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_fiscal_itens');
        Schema::dropIfExists('notas_fiscais');
    }
};