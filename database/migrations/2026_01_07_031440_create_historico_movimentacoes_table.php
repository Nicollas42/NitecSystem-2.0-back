<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historico_movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loja_id')->constrained('lojas');
            $table->foreignId('produto_id')->constrained('produtos');
            
            // Tipos: 'ENTRADA', 'TRANSFERENCIA', 'PERDA'
            $table->string('tipo_operacao'); 
            
            // De onde saiu e para onde foi (Ex: Origem: DEPOSITO, Destino: VITRINE)
            $table->string('origem')->nullable(); 
            $table->string('destino')->nullable();
            
            $table->decimal('quantidade', 10, 3);
            
            // O Motivo/Observação digitado
            $table->text('motivo')->nullable();
            
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historico_movimentacoes');
    }
};