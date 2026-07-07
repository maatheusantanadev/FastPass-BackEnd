<?php

namespace App\Services;

use App\Models\Compra;
use Illuminate\Http\JsonResponse;

/**
 * Regra única de "efetivar o embarque" de uma compra, reaproveitada tanto
 * pelo embarque direto (facial/QR feito pelo motorista) quanto pela
 * aprovação de um pedido de embarque solicitado pelo passageiro.
 */
class EmbarqueService
{
    public function efetuar(?Compra $compra, string $metodo): JsonResponse
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
}
