<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Excursao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompraController extends Controller
{
    /**
     * Lista as compras do passageiro autenticado.
     */
    public function index(Request $request): JsonResponse
    {
        $compras = $request->user()
            ->compras()
            ->with('excursao')
            ->latest()
            ->get();

        return response()->json($compras);
    }

    /**
     * Etapa 4 do fluxo: Compra efetuada.
     *
     * No MVP o pagamento é simulado: a compra já nasce confirmada,
     * com um código QR único gerado como alternativa de embarque.
     */
    public function store(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'excursao_id' => ['required', 'integer', 'exists:excursoes,id'],
        ]);

        $compra = DB::transaction(function () use ($dados, $request) {
            $excursao = Excursao::lockForUpdate()->findOrFail($dados['excursao_id']);

            if ($excursao->status !== Excursao::STATUS_ABERTA || $excursao->vagas_disponiveis <= 0) {
                abort(422, 'Não há vagas disponíveis para esta excursão.');
            }

            $jaComprou = Compra::where('user_id', $request->user()->id)
                ->where('excursao_id', $excursao->id)
                ->whereIn('status', [Compra::STATUS_CONFIRMADA, Compra::STATUS_EMBARCADA])
                ->exists();

            if ($jaComprou) {
                abort(422, 'Você já possui uma passagem ativa para esta excursão.');
            }

            $excursao->decrement('vagas_disponiveis');

            return Compra::create([
                'user_id'     => $request->user()->id,
                'excursao_id' => $excursao->id,
                'codigo_qr'   => (string) Str::uuid(),
                'valor'       => $excursao->preco,
                'status'      => Compra::STATUS_CONFIRMADA,
            ]);
        });

        return response()->json([
            'mensagem' => 'Compra realizada com sucesso. Pagamento confirmado.',
            'compra'   => $compra->load('excursao'),
        ], 201);
    }

    public function show(Request $request, Compra $compra): JsonResponse
    {
        abort_if($compra->user_id !== $request->user()->id, 403, 'Acesso negado.');

        return response()->json($compra->load('excursao'));
    }
}
