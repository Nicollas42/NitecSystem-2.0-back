<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lojas', function (Blueprint $table) {
            $table->id();
            // Chaves Estrangeiras
            $table->foreignId('empresa_id')->constrained('empresas')->onDelete('cascade');
            // Referência ao usuário (dono/criador). Usamos 'usuarios' (nome da tabela)
            $table->foreignId('user_id')->nullable()->constrained('usuarios')->onDelete('set null');

            $table->string('nome_fantasia');
            $table->string('cnpj', 20);
            $table->string('cnpj_matriz', 20)->nullable();
            $table->string('telefone', 20)->nullable();
            $table->string('endereco')->nullable();
            $table->boolean('eh_matriz')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lojas');
    }
};
