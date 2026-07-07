<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\PedidoEmbarque;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedidoEmbarqueController extends Controller
{
    /**
     * O passageiro tira uma selfie e solicita o embarque. O pedido fica
     * pendente até o motorista comparar as imagens e aprovar/reprovar —
     * o próprio passageiro nunca confirma o embarque da própria viagem.
     */
    public function solicitar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'compra_id' => ['required', 'integer', 'exists:compras,id'],
            'imagem'    => ['required', 'string'], // selfie em base64
        ]);

        $compra = Compra::findOrFail($dados['compra_id']);

        abort_if($compra->user_id !== $request->user()->id, 403, 'Acesso negado.');

        if ($compra->status !== Compra::STATUS_CONFIRMADA) {
            return response()->json([
                'mensagem' => 'A passagem não está apta para embarque (status: '.$compra->status.').',
            ], 422);
        }

        $pendente = $compra->pedidosEmbarque()
            ->where('status', PedidoEmbarque::STATUS_PENDENTE)
            ->first();

        if ($pendente) {
            return response()->json([
                'mensagem' => 'Você já tem um pedido de embarque aguardando validação do motorista.',
                'pedido'   => $pendente,
            ], 422);
        }

        $pedido = $compra->pedidosEmbarque()->create([
            'foto_enviada' => $dados['imagem'],
            'status'       => PedidoEmbarque::STATUS_PENDENTE,
        ]);

        return response()->json([
            'mensagem' => 'Pedido de embarque enviado. Aguarde a validação do motorista.',
            'pedido'   => $pedido,
        ], 201);
    }

    /**
     * O passageiro consulta o andamento do próprio pedido (para saber se foi
     * aprovado, reprovado ou ainda está pendente).
     */
    public function meuPedido(Request $request, Compra $compra): JsonResponse
    {
        abort_if($compra->user_id !== $request->user()->id, 403, 'Acesso negado.');

        $pedido = $compra->pedidosEmbarque()->latest()->first();

        return response()->json(['pedido' => $pedido]);
    }
}
