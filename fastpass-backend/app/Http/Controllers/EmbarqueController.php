<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Services\FacialRecognitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmbarqueController extends Controller
{
    public function __construct(
        protected FacialRecognitionService $facial
    ) {}

    /**
     * Etapa 6 do fluxo: Validação de facial no embarque.
     *
     * A imagem capturada no ponto de embarque é enviada à API de
     * reconhecimento, que devolve o passageiro identificado. A compra
     * correspondente é então marcada como embarcada.
     */
    public function porFacial(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'excursao_id' => ['required', 'integer', 'exists:excursoes,id'],
            'imagem'      => ['required', 'string'], // imagem em base64
        ]);

        // Restringe a busca aos passageiros desta excursão que já cadastraram a
        // biometria — mais rápido, mais preciso e evita falsos positivos com
        // passageiros de outras viagens. (A face é vinculada ao usuário.)
        $candidatos = Compra::where('excursao_id', $dados['excursao_id'])
            ->whereHas('user', fn ($q) => $q->where('facial_registrada', true))
            ->pluck('user_id')
            ->all();

        $resultado = $this->facial->verificar($dados['imagem'], $candidatos);

        if (! $resultado['sucesso']) {
            return response()->json([
                'mensagem' => 'Passageiro não reconhecido.',
                'detalhe'  => $resultado['erro'] ?? null,
            ], 404);
        }

        $compra = Compra::where('user_id', $resultado['user_id'])
            ->where('excursao_id', $dados['excursao_id'])
            ->whereIn('status', [Compra::STATUS_CONFIRMADA, Compra::STATUS_EMBARCADA])
            ->latest()
            ->first();

        return $this->efetuarEmbarque($compra, 'facial');
    }

    /**
     * Alternativa de embarque por QR Code.
     */
    public function porQrCode(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'codigo_qr' => ['required', 'uuid'],
        ]);

        $compra = Compra::where('codigo_qr', $dados['codigo_qr'])->first();

        return $this->efetuarEmbarque($compra, 'qr');
    }

    protected function efetuarEmbarque(?Compra $compra, string $metodo): JsonResponse
    {
        if (! $compra) {
            return response()->json([
                'mensagem' => 'Nenhuma passagem válida encontrada para este passageiro.',
            ], 404);
        }

        if ($compra->status === Compra::STATUS_EMBARCADA) {
            return response()->json([
                'mensagem' => 'Passageiro já embarcado.',
                'compra'   => $compra->load('user:id,name', 'excursao:id,titulo,destino'),
            ], 422);
        }

        if ($compra->status !== Compra::STATUS_CONFIRMADA) {
            return response()->json([
                'mensagem' => 'A passagem não está apta para embarque (status: '.$compra->status.').',
            ], 422);
        }

        $compra->update([
            'status'          => Compra::STATUS_EMBARCADA,
            'metodo_embarque' => $metodo,
            'embarcado_em'    => now(),
        ]);

        return response()->json([
            'mensagem' => 'Embarque autorizado via '.$metodo.'.',
            'compra'   => $compra->fresh()->load('user:id,name', 'excursao:id,titulo,destino'),
        ]);
    }
}
