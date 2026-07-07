<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Excursao;
use App\Services\EmbarqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmbarqueController extends Controller
{
    public function __construct(
        protected EmbarqueService $embarqueService
    ) {}

    /**
     * Embarque por QR Code, feito pelo motorista (lê o código do passageiro).
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

        return $this->embarqueService->efetuar($compra, 'qrcode');
    }

    /**
     * Conferência manual: o motorista confirma o embarque de um passageiro
     * já localizado na lista de embarque da viagem.
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

        return $this->embarqueService->efetuar($compra, 'manual');
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
