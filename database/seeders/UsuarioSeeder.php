<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Classe responsável por popular o banco com usuários iniciais.
 */
class UsuarioSeeder extends Seeder
{
    /**
     * Executa o cadastro do administrador padrão.
     * * @return void
     */
    public function run(): void
    {
        // Criando o usuário Admin
        // Lembre-se: 'nome_completo' é o nome da coluna que criamos na migration
        User::create([
            'nome_completo' => 'Administrador Principal',
            'email'         => 'admin@erp.com',
            'password'      => Hash::make('senha123'), // A senha será criptografada
        ]);
    }
}