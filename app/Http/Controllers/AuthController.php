<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
// --- ADICIONE ESTA LINHA OBRIGATÓRIA AQUI: ---
use App\Models\User;
// ---------------------------------------------

class AuthController extends Controller
{
    /**
     * CADASTRO DE NOVO USUÁRIO
     */
    public function register(Request $request)
    {
        $dados = $request->validate([
            'nome_completo' => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:usuarios',
            'celular'       => 'nullable|string|max:20|unique:usuarios',
            'password'      => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'nome_completo' => $dados['nome_completo'],
            'email'         => $dados['email'],
            'celular'       => $dados['celular'] ?? null,
            'password'      => Hash::make($dados['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'sucesso',
            'mensagem' => 'Cadastro realizado com sucesso!',
            'access_token' => $token,
            'usuario' => $user
        ], 201);
    }

    /**
     * LOGIN (Aceita E-mail OU Telemóvel)
     */
    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required', 
            'password' => 'required'
        ]);

        // Verifica se é email ou telemóvel
        $campo_busca = filter_var($request->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'celular';

        if (!Auth::attempt([$campo_busca => $request->login, 'password' => $request->password])) {
            return response()->json([
                'status'   => 'erro',
                'mensagem' => 'Credenciais incorretas (verifique e-mail/telemóvel ou senha).'
            ], 401);
        }

        $usuario = Auth::user();
        $token_acesso = $usuario->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status'       => 'sucesso',
            'mensagem'     => 'Login realizado!',
            'access_token' => $token_acesso,
            'usuario'      => $usuario
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['mensagem' => 'Logout realizado.']);
    }
}