<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Services\PagamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagamentoWebhookController extends Controller
{
    /**
     * Webhook do Mercado Pago.
     *
     * Recebe a notificação de pagamento, consulta o status no PSP (fonte da
     * verdade) e confirma a compra correspondente. Rota pública; em produção
     * exige URL acessível (o Laravel deve estar publicado).
     */
    public function mercadopago(Request $request, PagamentoService $pagamento): JsonResponse
    {
        $paymentId = $request->input('data.id')
            ?? $request->query('data_id')
            ?? $request->query('id');

        if ($paymentId) {
            $compra = Compra::where('pagamento_id', (string) $paymentId)->first();

            if ($compra && $compra->status === Compra::STATUS_PENDENTE) {
                $res = $pagamento->consultarStatus((string) $paymentId);
                if (($res['status'] ?? null) === 'aprovado') {
                    $compra->confirmarPagamento();
                }
            }
        }

        // Sempre 200 para o PSP não reenfileirar a notificação.
        return response()->json(['ok' => true]);
    }
}
