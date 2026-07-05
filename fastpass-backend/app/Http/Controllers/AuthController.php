<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FacialRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Etapa 1 do fluxo: Cadastro do passageiro.
     */
    public function register(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'cpf'      => ['nullable', 'string', 'max:14', 'unique:users,cpf'],
            'telefone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create($dados);

        $token = $user->createToken('fastpass')->plainTextToken;

        return response()->json([
            'mensagem' => 'Cadastro realizado com sucesso.',
            'usuario'  => $user,
            'token'    => $token,
        ], 201);
    }

    /**
     * Etapa 2 do fluxo: Login.
     */
    public function login(Request $request): JsonResponse
    {
        $credenciais = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credenciais['email'])->first();

        if (! $user || ! Hash::check($credenciais['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciais inválidas.'],
            ]);
        }

        $token = $user->createToken('fastpass')->plainTextToken;

        return response()->json([
            'mensagem' => 'Login realizado com sucesso.',
            'usuario'  => $user,
            'token'    => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['mensagem' => 'Sessão encerrada.']);
    }

    /**
     * Cadastro da biometria facial do usuário autenticado.
     *
     * A face fica vinculada ao id do usuário no serviço FastPass-Facial, então
     * o cadastro é feito uma vez por conta (ex.: no onboarding). No embarque, a
     * identificação devolve esse mesmo id.
     */
    public function registrarFacial(Request $request, FacialRecognitionService $facial): JsonResponse
    {
        $dados = $request->validate([
            'imagem' => ['required', 'string'], // imagem em base64 (data URI aceito)
        ]);

        $user = $request->user();

        $resultado = $facial->registrar($user->id, $dados['imagem'], $user->name);

        if (! $resultado['sucesso']) {
            return response()->json([
                'mensagem' => 'Falha ao registrar a biometria facial.',
                'detalhe'  => $resultado['erro'] ?? null,
            ], 502);
        }

        $user->update([
            'facial_registrada' => true,
            'facial_id'         => $resultado['facial_id'],
        ]);

        return response()->json([
            'mensagem' => 'Biometria facial registrada com sucesso.',
            'usuario'  => $user->fresh(),
        ]);
    }
}
