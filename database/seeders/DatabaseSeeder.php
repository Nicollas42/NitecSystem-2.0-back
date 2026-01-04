<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('pt_BR');

        // 1. CONFIGURAÇÕES GLOBAIS
        DB::table('configuracoes_custos')->insertOrIgnore([
            'id' => 1, 'custo_energia_kwh' => 0.95, 'custo_gas_kg' => 9.50,
            'custo_agua_m3' => 12.00, 'custo_mao_obra_hora' => 15.50
        ]);

        // 2. CRIAR EMPRESA EXEMPLO
        $empresaId = DB::table('empresas')->insertGetId([
            'razao_social' => 'Padaria do João LTDA',
            'cnpj_raiz' => '12345678',
            'responsavel_nome' => 'João da Silva',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 3. CRIAR USUÁRIO DONO
        $userId = DB::table('usuarios')->insertGetId([
            'empresa_id' => $empresaId,
            'nome_completo' => 'João Admin',
            'email' => 'admin@teste.com',
            'password' => Hash::make('123456'), // Usando Hash::make pois é inserção direta
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 4. CRIAR LOJAS (Matriz e Filial)
        $lojaMatrizId = DB::table('lojas')->insertGetId([
            'empresa_id' => $empresaId, 'user_id' => $userId,
            'nome_fantasia' => 'Padaria João - MATRIZ (Bairro Nobre)',
            'cnpj' => '12345678000199', 'eh_matriz' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $lojaFilialId = DB::table('lojas')->insertGetId([
            'empresa_id' => $empresaId, 'user_id' => $userId,
            'nome_fantasia' => 'Padaria João - FILIAL (Bairro Popular)',
            'cnpj' => '12345678000288', 'eh_matriz' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 5. CADASTRAR PRODUTO (Catálogo Geral)
        // Coca-Cola (Preço base sugerido: 10.00)
        $prodId = DB::table('produtos')->insertGetId([
            'empresa_id' => $empresaId,
            'nome' => 'Coca-Cola 2L',
            'tipo_item' => 'REVENDA',
            'preco_venda' => 10.00, // Preço "Capa"
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // 6. DISTRIBUIR NOS ESTOQUES (Com preços diferentes!)
        
        // Na Matriz: Preço R$ 10,00 | Estoque 50
        DB::table('estoque_lojas')->insert([
            'loja_id' => $lojaMatrizId,
            'produto_id' => $prodId,
            'quantidade' => 50,
            'preco_venda' => 10.00, // Segue o preço base
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Na Filial: Preço R$ 9,50 | Estoque 100
        DB::table('estoque_lojas')->insert([
            'loja_id' => $lojaFilialId,
            'produto_id' => $prodId,
            'quantidade' => 100,
            'preco_venda' => 9.50, // <--- PREÇO DIFERENTE AQUI
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->command->info('✅ Teste criado: Coca-Cola custa R$ 10 na Matriz e R$ 9,50 na Filial!');
    }
}