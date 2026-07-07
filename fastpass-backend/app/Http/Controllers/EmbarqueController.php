<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Excursao;
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

        $excursao = Excursao::findOrFail($dados['excursao_id']);
        $this->autorizarViagem($request, $excursao);

        $resultado = $this->facial->verificar($dados['imagem']);

        if (! $resultado['sucesso']) {
            return response()->json([
                'mensagem' => 'Passageiro não reconhecido.',
                'detalhe'  => $resultado['erro'] ?? null,
            ], 404);
        }

        $compra = Compra::where('user_id', $resultado['user_id'])
            ->where('excursao_id', $dados['excursao_id'])
            ->where('facial_registrada', true)
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

        if ($compra) {
            $this->autorizarViagem($request, $compra->excursao);
        }

        return $this->efetuarEmbarque($compra, 'qrcode');
    }

    /**
     * Conferência manual: o motorista/operador confirma o embarque de um
     * passageiro já localizado na lista (a busca por nome/CPF é feita no
     * front-end sobre a lista trazida por MotoristaController::embarque).
     */
    public function porManual(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'compra_id' => ['required', 'integer', 'exists:compras,id'],
        ]);

        $compra = Compra::find($dados['compra_id']);

        if ($compra) {
            $this->autorizarViagem($request, $compra->excursao);
        }

        return $this->efetuarEmbarque($compra, 'manual');
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
                'compra'   => $compra->load('user:id,name,cpf', 'excursao:id,titulo,destino'),
            ], 422);
        }

        if ($compra->status !== Compra::STATUS_CONFIRMADA) {
            return response()->json([
                'mensagem' => 'A passagem não está apta para embarque (status: '.$compra->status.').',
            ], 422);
        }

        $compra->update([
            'status'          => Compra::STATUS_EMBARCADA,
            'embarcado_em'    => now(),
            'metodo_embarque' => $metodo,
        ]);

        return response()->json([
            'mensagem' => 'Embarque autorizado via '.$metodo.'.',
            'compra'   => $compra->fresh()->load('user:id,name,cpf', 'excursao:id,titulo,destino'),
        ]);
    }

    /**
     * Só o motorista designado para a viagem (ou um administrador) pode
     * validar embarques dela.
     */
    protected function autorizarViagem(Request $request, Excursao $excursao): void
    {
        $usuario = $request->user();

        abort_if(
            ! $usuario->isAdministrador() && $excursao->motorista_id !== $usuario->id,
            403,
            'Esta viagem não está atribuída a você.'
        );
    }
}
