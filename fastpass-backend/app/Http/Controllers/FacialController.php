<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Services\FacialRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FacialController extends Controller
{
    public function __construct(
        protected FacialRecognitionService $facial
    ) {}

    /**
     * Etapa 5 do fluxo: Registro da biometria facial vinculada à compra.
     *
     * Recebe a imagem em base64, envia para a API de reconhecimento
     * facial e armazena o identificador biométrico na compra.
     */
    public function registrar(Request $request, Compra $compra): JsonResponse
    {
        abort_if($compra->user_id !== $request->user()->id, 403, 'Acesso negado.');

        if ($compra->status !== Compra::STATUS_CONFIRMADA) {
            return response()->json([
                'mensagem' => 'A biometria só pode ser registrada em compras confirmadas.',
            ], 422);
        }

        $dados = $request->validate([
            'imagem' => ['required', 'string'], // imagem em base64
        ]);

        $resultado = $this->facial->registrar($request->user()->id, $dados['imagem']);

        if (! $resultado['sucesso']) {
            return response()->json([
                'mensagem' => 'Falha ao registrar a biometria facial.',
                'detalhe'  => $resultado['erro'] ?? null,
            ], 502);
        }

        $compra->update([
            'facial_registrada' => true,
            'facial_id'         => $resultado['facial_id'],
        ]);

        return response()->json([
            'mensagem' => 'Biometria facial registrada com sucesso.',
            'compra'   => $compra->fresh(),
        ]);
    }
}
