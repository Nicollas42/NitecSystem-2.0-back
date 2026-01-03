<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $faker = Faker::create('pt_BR'); // Gera dados em Português

        // 1. CONFIGURAÇÕES GLOBAIS (Tarifas)
        DB::table('configuracoes_custos')->insertOrIgnore([
            'id' => 1,
            'custo_energia_kwh' => 0.95,
            'custo_gas_kg' => 9.50,
            'custo_agua_m3' => 12.00,
            'custo_mao_obra_hora' => 15.50,
            'updated_at' => now(),
        ]);

        $this->command->info('✅ Tarifas Configuradas!');

        // 2. EQUIPAMENTOS PADRÃO (Compartilhados ou Globais)
        $equipamentos = [
            ['nome' => 'Forno Turbo 10 Assadeiras', 'tipo' => 'ELETRICO', 'potencia' => 18000, 'gas' => 0, 'valor' => 15000],
            ['nome' => 'Batedeira Planetária 20L', 'tipo' => 'ELETRICO', 'potencia' => 1500, 'gas' => 0, 'valor' => 4500],
            ['nome' => 'Forno de Lastro a Gás', 'tipo' => 'GAS', 'potencia' => 0, 'gas' => 0.800, 'valor' => 12000],
            ['nome' => 'Modeladora de Pães', 'tipo' => 'ELETRICO', 'potencia' => 1000, 'gas' => 0, 'valor' => 5800],
        ];

        foreach ($equipamentos as $eq) {
            DB::table('equipamentos')->insert([
                'nome' => $eq['nome'],
                'tipo_energia' => $eq['tipo'],
                'potencia_watts' => $eq['potencia'],
                'consumo_gas_kg_h' => $eq['gas'],
                'valor_aquisicao' => $eq['valor'],
                'valor_residual' => $eq['valor'] * 0.2, // 20% residual
                'vida_util_anos' => 10,
                'depreciacao_hora' => (($eq['valor'] - ($eq['valor'] * 0.2)) / 10) / (12 * 220), // Cálculo aprox.
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('✅ Equipamentos Criados!');

        // 3. CRIAR 3 CLIENTES (EMPRESAS + USUÁRIOS + LOJAS)
        for ($i = 1; $i <= 3; $i++) {
            
            // A. Cria Empresa
            $cnpjMatriz = $faker->cnpj(false);
            $empresaId = DB::table('empresas')->insertGetId([
                'razao_social' => $faker->company . ' LTDA',
                'cnpj_raiz' => substr($cnpjMatriz, 0, 8),
                'responsavel_nome' => $faker->name,
                'responsavel_cpf' => $faker->cpf(false),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // B. Cria Usuário Dono (Senha padrão: 123456)
            $email = "admin{$i}@teste.com";
            $userId = DB::table('usuarios')->insertGetId([
                'empresa_id' => $empresaId,
                'nome_completo' => "Dono da Empresa {$i}",
                'email' => $email,
                'cpf' => $faker->cpf(false),
                'celular' => $faker->cellphoneNumber,
                'password' => Hash::make('123456'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // C. Cria Matriz
            DB::table('lojas')->insert([
                'empresa_id' => $empresaId,
                'user_id' => $userId,
                'nome_fantasia' => "Padaria Matriz {$i}",
                'cnpj' => $cnpjMatriz,
                'cnpj_matriz' => null,
                'telefone' => $faker->landline,
                'endereco' => $faker->address,
                'eh_matriz' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // D. Cria 2 Filiais para cada empresa
            for ($j = 1; $j <= 2; $j++) {
                DB::table('lojas')->insert([
                    'empresa_id' => $empresaId,
                    'user_id' => $userId,
                    'nome_fantasia' => "Filial {$j} - Empresa {$i}",
                    'cnpj' => $faker->cnpj(false),
                    'cnpj_matriz' => $cnpjMatriz,
                    'telefone' => $faker->landline,
                    'endereco' => $faker->address,
                    'eh_matriz' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->command->info("🏢 Empresa {$i} criada com usuário: {$email} / 123456");
        }

        // 4. PRODUTOS (INSUMOS E REVENDA)
        // Vamos criar produtos aleatórios, mas sem vincular a tenant ainda (ou global, dependendo da sua regra)
        // Assumindo que produtos por enquanto são globais ou só para teste
        
        $insumos = [
            ['nome' => 'Farinha de Trigo Especial', 'un' => 'KG', 'custo' => 4.50, 'tipo' => 'INSUMOS'],
            ['nome' => 'Açúcar Refinado', 'un' => 'KG', 'custo' => 3.20, 'tipo' => 'INSUMOS'],
            ['nome' => 'Ovos Vermelhos (Cartela 30)', 'un' => 'UN', 'custo' => 18.00, 'tipo' => 'INSUMOS'],
            ['nome' => 'Fermento Biológico Fresco', 'un' => 'KG', 'custo' => 12.00, 'tipo' => 'INSUMOS'],
            ['nome' => 'Leite Integral', 'un' => 'L', 'custo' => 4.80, 'tipo' => 'INSUMOS'],
            ['nome' => 'Margarina 80% Lipídios', 'un' => 'KG', 'custo' => 14.50, 'tipo' => 'INSUMOS'],
        ];

        $revenda = [
            ['nome' => 'Coca-Cola 2L', 'cat' => 'Bebidas', 'custo' => 7.00, 'venda' => 12.00, 'tipo' => 'REVENDA'],
            ['nome' => 'Suco Del Valle Uva', 'cat' => 'Bebidas', 'custo' => 5.50, 'venda' => 9.00, 'tipo' => 'REVENDA'],
            ['nome' => 'Queijo Mussarela', 'cat' => 'Frios', 'custo' => 38.00, 'venda' => 55.00, 'tipo' => 'REVENDA', 'un' => 'KG'],
            ['nome' => 'Presunto Cozido', 'cat' => 'Frios', 'custo' => 25.00, 'venda' => 40.00, 'tipo' => 'REVENDA', 'un' => 'KG'],
        ];

        // Insere Insumos
        foreach ($insumos as $in) {
            DB::table('produtos')->insert([
                'tipo_item' => $in['tipo'],
                'nome' => $in['nome'],
                'unidade_medida' => $in['un'] ?? 'UN',
                'preco_custo' => $in['custo'],
                'preco_venda' => 0, // Insumo não vende direto
                'estoque_atual' => rand(10, 100),
                'estoque_minimo' => 5,
                'grupo_familia' => 'Matéria Prima',
                'categoria' => 'Padaria',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insere Revenda
        foreach ($revenda as $re) {
            DB::table('produtos')->insert([
                'tipo_item' => $re['tipo'],
                'nome' => $re['nome'],
                'unidade_medida' => $re['un'] ?? 'UN',
                'preco_custo' => $re['custo'],
                'preco_venda' => $re['venda'],
                'estoque_atual' => rand(20, 50),
                'estoque_minimo' => 10,
                'grupo_familia' => 'Mercearia',
                'categoria' => $re['cat'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Insere alguns Fabricados (Interno)
        DB::table('produtos')->insert([
            'tipo_item' => 'INTERNO',
            'nome' => 'Pão Francês (Kg)',
            'unidade_medida' => 'KG',
            'preco_custo' => 6.50, // Estimado
            'preco_venda' => 14.90,
            'estoque_atual' => 0, // Produção diária
            'estoque_minimo' => 0,
            'grupo_familia' => 'Produção Própria',
            'categoria' => 'Padaria',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('✅ Produtos Cadastrados!');
    }
}