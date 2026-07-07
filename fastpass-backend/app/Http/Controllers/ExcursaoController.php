<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Excursao;
use Illuminate\Http\JsonResponse;

class ExcursaoController extends Controller
{
    /**
     * Etapa 3 do fluxo: Dashboard com excursões disponíveis para compra.
     */
    public function index(): JsonResponse
    {
        return response()->json(Excursao::disponiveis()->get());
    }

    public function show(Excursao $excursao): JsonResponse
    {
        return response()->json($excursao);
    }

    /**
     * Painel de gestão da excursão (visão da empresa):
     * vagas, confirmados, embarcados e ocupação em tempo real.
     */
    public function painel(Excursao $excursao): JsonResponse
    {
        $compras = $excursao->compras()->with('user:id,name,email')->get();

        $confirmados = $compras->whereIn('status', [
            Compra::STATUS_CONFIRMADA,
            Compra::STATUS_EMBARCADA,
            Compra::STATUS_CONCLUIDA,
        ]);

        $embarcados = $compras->whereIn('status', [
            Compra::STATUS_EMBARCADA,
            Compra::STATUS_CONCLUIDA,
        ]);

        return response()->json([
            'excursao'            => $excursao,
            'vagas_total'         => $excursao->vagas_total,
            'vagas_disponiveis'   => $excursao->vagas_disponiveis,
            'passageiros_confirmados' => $confirmados->count(),
            'passageiros_embarcados'  => $embarcados->count(),
            'ocupacao_percentual' => $excursao->vagas_total > 0
                ? round(($confirmados->count() / $excursao->vagas_total) * 100, 1)
                : 0,
            'lista_embarque'      => $compras->values(),
        ]);
    }

    /**
     * Etapa 7 do fluxo: Viagem concluída.
     * Encerra a excursão e marca as compras embarcadas como concluídas.
     */
    public function concluir(Excursao $excursao): JsonResponse
    {
        if ($excursao->status === Excursao::STATUS_CONCLUIDA) {
            return response()->json([
                'mensagem' => 'Esta excursão já foi concluída.',
            ], 422);
        }

        $excursao->update(['status' => Excursao::STATUS_CONCLUIDA]);

        $excursao->compras()
            ->where('status', Compra::STATUS_EMBARCADA)
            ->update(['status' => Compra::STATUS_CONCLUIDA]);

        return response()->json([
            'mensagem' => 'Viagem concluída com sucesso.',
            'excursao' => $excursao->fresh(),
        ]);
    }
}
