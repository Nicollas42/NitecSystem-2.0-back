<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash; // Não esqueça de importar o Hash

class PasswordResetController extends Controller
{
    /**
     * Gera o token e envia o e-mail de recuperação.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function enviar_link_recuperacao(Request $request)
    {
        // 1. Validação
        $request->validate(['email' => 'required|email|exists:usuarios,email']);

        $email_usuario = $request->email;

        // 2. Gerar Token Único
        $token_gerado = Str::random(60);

        // 3. Limpar tokens antigos
        DB::table('password_reset_tokens')->where('email', $email_usuario)->delete();

        // 4. Salvar novo token
        DB::table('password_reset_tokens')->insert([
            'email' => $email_usuario,
            'token' => $token_gerado,
            'created_at' => Carbon::now()
        ]);

        $link_recuperacao = "http://localhost:5173/redefinir-senha?token=" . $token_gerado . "&email=" . $email_usuario;

        // --- CORREÇÃO AQUI: Adicione o 'try {' ---
        try {
            Mail::send([], [], function ($message) use ($email_usuario, $link_recuperacao) {
                $message->to($email_usuario)
                        ->subject('Recuperação de Senha - Nitec ERP')
                        ->html("
                            <h1>Recuperação de Senha</h1>
                            <p>Clique no link para resetar:</p>
                            <a href='$link_recuperacao'>Redefinir Senha</a>
                        ");
            });

            return response()->json([
                'status' => 'sucesso',
                'mensagem' => 'Link enviado com sucesso!'
            ]);

        } catch (\Exception $erro) {
            return response()->json([
                'status' => 'erro',
                'mensagem' => 'Erro ao enviar e-mail: ' . $erro->getMessage()
            ], 500);
        }
    }

    public function redefinir_senha_final(Request $request)
    {
        // 1. Validar dados recebidos
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email|exists:usuarios,email',
            'password' => 'required|string|min:6|confirmed', // 'confirmed' exige que venha password_confirmation
        ]);

        // 2. Verificar se o token existe no banco e bate com o email
        $registro_token = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$registro_token) {
            return response()->json([
                'status' => 'erro', 
                'mensagem' => 'Token inválido ou expirado.'
            ], 400);
        }

        // 3. Atualizar a senha do usuário
        $usuario = User::where('email', $request->email)->first();
        $usuario->password = Hash::make($request->password);
        $usuario->save();

        // 4. Deletar o token usado (para não ser usado de novo)
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'status' => 'sucesso',
            'mensagem' => 'Senha alterada com sucesso!'
        ]);
    }
}
