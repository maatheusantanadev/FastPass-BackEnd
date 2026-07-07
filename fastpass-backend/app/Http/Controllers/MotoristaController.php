<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Excursao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MotoristaController extends Controller
{
    /**
     * Viagens atribuídas ao motorista autenticado (ainda não concluídas),
     * da mais próxima para a mais distante.
     */
    public function excursoes(Request $request): JsonResponse
    {
        $excursoes = Excursao::doMotorista($request->user()->id)->get();

        return response()->json($excursoes);
    }

    /**
     * Lista de embarque (passageiros + contadores) de uma viagem do motorista.
     * Mesma lógica do painel de gestão, mas restrita à viagem do motorista logado.
     */
    public function embarque(Request $request, Excursao $excursao): JsonResponse
    {
        $this->autorizarExcursao($request, $excursao);

        $compras = $excursao->compras()->with('user:id,name,email,cpf')->get();

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
            'excursao'                => $excursao,
            'vagas_total'             => $excursao->vagas_total,
            'passageiros_confirmados' => $confirmados->count(),
            'passageiros_embarcados'  => $embarcados->count(),
            'lista_embarque'          => $compras->values(),
        ]);
    }

    /**
     * Encerra o embarque da viagem: marca a excursão como concluída e todas
     * as compras embarcadas como concluídas (reaproveita a mesma regra usada
     * pela gestão via ExcursaoController::concluir).
     */
    public function concluir(Request $request, Excursao $excursao): JsonResponse
    {
        $this->autorizarExcursao($request, $excursao);

        if ($excursao->status === Excursao::STATUS_CONCLUIDA) {
            return response()->json([
                'mensagem' => 'Esta viagem já foi concluída.',
            ], 422);
        }

        $excursao->update(['status' => Excursao::STATUS_CONCLUIDA]);

        $excursao->compras()
            ->where('status', Compra::STATUS_EMBARCADA)
            ->update(['status' => Compra::STATUS_CONCLUIDA]);

        return response()->json([
            'mensagem' => 'Embarque encerrado com sucesso.',
            'excursao' => $excursao->fresh(),
        ]);
    }

    /**
     * Garante que a viagem pertence ao motorista autenticado
     * (administradores têm acesso a qualquer viagem).
     */
    protected function autorizarExcursao(Request $request, Excursao $excursao): void
    {
        $usuario = $request->user();

        abort_if(
            ! $usuario->isAdministrador() && $excursao->motorista_id !== $usuario->id,
            403,
            'Esta viagem não está atribuída a você.'
        );
    }
}
