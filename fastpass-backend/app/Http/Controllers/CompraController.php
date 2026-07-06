<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Excursao;
use App\Services\PagamentoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompraController extends Controller
{
    /**
     * Lista as passagens pagas do passageiro autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $compras = $request->user()
            ->compras()
            ->whereNotIn('status', [Compra::STATUS_PENDENTE, Compra::STATUS_CANCELADA])
            ->with('excursao')
            ->latest()
            ->get();

        return response()->json($compras);
    }

    /**
     * Etapa 4 do fluxo: Checkout.
     *
     * Cria a compra como pendente e gera uma cobrança Pix (Mercado Pago). A
     * vaga só é debitada quando o pagamento é confirmado.
     */
    public function store(Request $request, PagamentoService $pagamento): JsonResponse
    {
        $dados = $request->validate([
            'excursao_id' => ['required', 'integer', 'exists:excursoes,id'],
        ]);

        $user = $request->user();
        $excursao = Excursao::findOrFail($dados['excursao_id']);

        if ($excursao->status !== Excursao::STATUS_ABERTA || $excursao->vagas_disponiveis <= 0) {
            abort(422, 'Não há vagas disponíveis para esta excursão.');
        }

        $jaTem = Compra::where('user_id', $user->id)
            ->where('excursao_id', $excursao->id)
            ->whereIn('status', [Compra::STATUS_CONFIRMADA, Compra::STATUS_EMBARCADA])
            ->exists();

        if ($jaTem) {
            abort(422, 'Você já possui uma passagem ativa para esta excursão.');
        }

        // Descarta cobranças Pix pendentes anteriores deste passageiro na excursão.
        Compra::where('user_id', $user->id)
            ->where('excursao_id', $excursao->id)
            ->where('status', Compra::STATUS_PENDENTE)
            ->update(['status' => Compra::STATUS_CANCELADA]);

        $compra = Compra::create([
            'user_id'     => $user->id,
            'excursao_id' => $excursao->id,
            'codigo_qr'   => (string) Str::uuid(),
            'valor'       => $excursao->preco,
            'status'      => Compra::STATUS_PENDENTE,
        ]);

        $cobranca = $pagamento->criarCobrancaPix($compra->load('excursao'), $user);

        if (! $cobranca['sucesso']) {
            $compra->update(['status' => Compra::STATUS_CANCELADA]);

            return response()->json([
                'mensagem' => 'Não foi possível gerar a cobrança Pix.',
                'detalhe'  => $cobranca['erro'] ?? null,
            ], 502);
        }

        $compra->update([
            'pagamento_id'   => $cobranca['pagamento_id'],
            'pix_copia_cola' => $cobranca['copia_cola'],
        ]);

        return response()->json([
            'mensagem' => 'Cobrança Pix gerada. Escaneie para pagar.',
            'compra'   => $compra->fresh()->load('excursao'),
            'pix'      => [
                'copia_cola'   => $cobranca['copia_cola'],
                'qr_base64'    => $cobranca['qr_base64'],
                'pagamento_id' => $cobranca['pagamento_id'],
                'simulado'     => $pagamento->simulado(),
            ],
        ], 201);
    }

    /**
     * Consulta o pagamento da compra e a confirma quando aprovado (polling).
     *
     * @return JsonResponse status: pendente | aprovado | recusado
     */
    public function pagamento(Request $request, Compra $compra, PagamentoService $pagamento): JsonResponse
    {
        abort_if($compra->user_id !== $request->user()->id, 403, 'Acesso negado.');

        if ($compra->status !== Compra::STATUS_PENDENTE) {
            return response()->json([
                'status' => $compra->status === Compra::STATUS_CANCELADA ? 'recusado' : 'aprovado',
                'compra' => $compra->load('excursao'),
            ]);
        }

        if (! $compra->pagamento_id) {
            return response()->json(['status' => 'pendente', 'compra' => $compra->load('excursao')]);
        }

        $res = $pagamento->consultarStatus($compra->pagamento_id);

        if (! empty($res['sucesso'])) {
            if ($res['status'] === 'aprovado') {
                $compra->confirmarPagamento();
            } elseif ($res['status'] === 'recusado') {
                $compra->update(['status' => Compra::STATUS_CANCELADA]);
            }
        }

        return response()->json([
            'status' => $res['status'] ?? 'pendente',
            'compra' => $compra->fresh()->load('excursao'),
        ]);
    }

    public function show(Request $request, Compra $compra): JsonResponse
    {
        abort_if($compra->user_id !== $request->user()->id, 403, 'Acesso negado.');

        return response()->json($compra->load('excursao'));
    }
}
