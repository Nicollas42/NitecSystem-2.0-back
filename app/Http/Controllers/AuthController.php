<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash; // Pode manter, usado no login/verificar_senha
use Illuminate\Support\Facades\DB; 
use App\Models\User;

class AuthController extends Controller
{
    /**
     * CADASTRO DE NOVO CLIENTE (ARQUITETURA MULTI-TENANT)
     */
    public function register(Request $request)
    {
        $request->validate([
            'nome'            => 'required|string|max:255',
            'email'           => 'required|string|email|max:255|unique:usuarios',
            'cpf'             => 'required|string|max:20', 
            'celular'         => 'required|string|max:20',
            'password'        => 'required|string|min:6|confirmed',
            'empresa.nome_fantasia' => 'required|string',
            'empresa.cnpj'          => 'required|string',
            'empresa.endereco'      => 'required|string',
        ]);

        DB::beginTransaction();

        try {
            // 1. CRIA A EMPRESA
            $empresaId = DB::table('empresas')->insertGetId([
                'razao_social'     => $request->empresa['nome_fantasia'], 
                'cnpj_raiz'        => $request->empresa['cnpj'], 
                'responsavel_nome' => $request->nome,
                'responsavel_cpf'  => $request->cpf,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // 2. CRIA O USUÁRIO
            // CORREÇÃO AQUI: Removemos Hash::make() pois o User.php já tem 'casts' => 'hashed'
            $user = User::create([
                'empresa_id'    => $empresaId,
                'nome_completo' => $request->nome, 
                'email'         => $request->email,
                'cpf'           => $request->cpf,
                'celular'       => $request->celular,
                'password'      => $request->password, // <--- MUDOU AQUI (Envia senha pura)
            ]);

            // 3. CRIA A MATRIZ
            DB::table('lojas')->insert([
                'empresa_id'    => $empresaId,
                'user_id'       => $user->id,
                'nome_fantasia' => $request->empresa['nome_fantasia'],
                'cnpj'          => $request->empresa['cnpj'],
                'telefone'      => $request->empresa['telefone'] ?? null,
                'endereco'      => $request->empresa['endereco'], 
                'eh_matriz'     => true,
                'cnpj_matriz'   => null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // 4. CRIA AS FILIAIS
            if (!empty($request->filiais)) {
                foreach ($request->filiais as $filial) {
                    if (!empty($filial['nome'])) {
                        DB::table('lojas')->insert([
                            'empresa_id'    => $empresaId,
                            'user_id'       => $user->id,
                            'nome_fantasia' => $filial['nome'],
                            'cnpj'          => $filial['cnpj'] ?? null,
                            'endereco'      => $filial['endereco'] ?? null,
                            'eh_matriz'     => false,
                            'cnpj_matriz'   => $request->empresa['cnpj'],
                            'created_at'    => now(),
                            'updated_at'    => now(),
                        ]);
                    }
                }
            }

            DB::commit();

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Cadastro realizado com sucesso!',
                'token' => $token,
                'usuario' => $user
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Erro ao salvar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $request->validate(['login' => 'required', 'password' => 'required']);
        $campo_busca = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'celular';

        if (!Auth::attempt([$campo_busca => $request->login, 'password' => $request->password])) {
            return response()->json(['status' => 'erro', 'mensagem' => 'Credenciais incorretas.'], 401);
        }

        $usuario = Auth::user();
        $token = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'sucesso', 
            'access_token' => $token, 
            'usuario' => $usuario
        ]);
    }

    public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['mensagem' => 'Logout realizado.']);
    }

    public function verificar_senha(Request $request)
    {
        $request->validate(['password' => 'required']);

        if (Hash::check($request->password, $request->user()->password)) {
            return response()->json(['status' => 'sucesso']);
        }

        return response()->json(['message' => 'Senha incorreta.'], 403);
    }
}