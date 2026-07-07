<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Excursao;
use App\Models\PedidoEmbarque;
use App\Services\EmbarqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MotoristaController extends Controller
{
    public function __construct(
        protected EmbarqueService $embarqueService
    ) {}

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
     * Pedidos de embarque pendentes de uma viagem: para cada um, traz a foto
     * enviada agora pelo passageiro E a foto de referência cadastrada, para
     * o motorista comparar visualmente antes de decidir.
     */
    public function pedidos(Request $request, Excursao $excursao): JsonResponse
    {
        $this->autorizarExcursao($request, $excursao);

        $pedidos = PedidoEmbarque::query()
            ->whereHas('compra', fn ($q) => $q->where('excursao_id', $excursao->id))
            ->where('status', PedidoEmbarque::STATUS_PENDENTE)
            ->with('compra.user:id,name,cpf')
            ->latest()
            ->get();

        return response()->json($pedidos);
    }

    /**
     * Aprova o pedido: o motorista comparou as fotos e confirma que é o
     * mesmo passageiro. Efetiva o embarque da compra correspondente.
     */
    public function aprovarPedido(Request $request, PedidoEmbarque $pedido): JsonResponse
    {
        $pedido->loadMissing('compra.excursao');
        $this->autorizarExcursao($request, $pedido->compra->excursao);

        if ($pedido->status !== PedidoEmbarque::STATUS_PENDENTE) {
            return response()->json(['mensagem' => 'Este pedido já foi resolvido.'], 422);
        }

        $resposta = $this->embarqueService->efetuar($pedido->compra, 'facial');

        // Só marca o pedido como aprovado se o embarque foi efetivado (2xx).
        if ($resposta->getStatusCode() < 300) {
            $pedido->update([
                'status'        => PedidoEmbarque::STATUS_APROVADO,
                'resolvido_por' => $request->user()->id,
                'resolvido_em'  => now(),
            ]);
        }

        return $resposta;
    }

    /**
     * Reprova o pedido: as fotos não batem (ou o motorista tem dúvida). O
     * passageiro pode enviar um novo pedido em seguida.
     */
    public function reprovarPedido(Request $request, PedidoEmbarque $pedido): JsonResponse
    {
        $pedido->loadMissing('compra.excursao');
        $this->autorizarExcursao($request, $pedido->compra->excursao);

        if ($pedido->status !== PedidoEmbarque::STATUS_PENDENTE) {
            return response()->json(['mensagem' => 'Este pedido já foi resolvido.'], 422);
        }

        $pedido->update([
            'status'        => PedidoEmbarque::STATUS_REPROVADO,
            'resolvido_por' => $request->user()->id,
            'resolvido_em'  => now(),
        ]);

        return response()->json([
            'mensagem' => 'Pedido reprovado. O passageiro pode tentar novamente.',
            'pedido'   => $pedido->fresh(),
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
