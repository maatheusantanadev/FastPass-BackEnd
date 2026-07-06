<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pagamento via Pix (Mercado Pago).
 *
 * Cria uma cobrança Pix dinâmica e consulta seu status. As credenciais ficam
 * em services.mercadopago. Sem access token (ou com MERCADOPAGO_FAKE=true), o
 * serviço opera em modo simulado: gera um copia-e-cola fictício e aprova
 * automaticamente — útil para demonstrações sem uma conta de PSP.
 */
class PagamentoService
{
    protected ?string $accessToken;
    protected bool $modoSimulado;
    protected string $baseUrl = 'https://api.mercadopago.com';

    public function __construct()
    {
        $this->accessToken  = config('services.mercadopago.access_token');
        $this->modoSimulado = (bool) config('services.mercadopago.fake') || ! $this->accessToken;
    }

    public function simulado(): bool
    {
        return $this->modoSimulado;
    }

    /**
     * Cria uma cobrança Pix para a compra.
     *
     * @return array{sucesso:bool, pagamento_id?:string, copia_cola?:string, qr_base64?:string, status?:string, erro?:string}
     */
    public function criarCobrancaPix(Compra $compra, User $user): array
    {
        if ($this->modoSimulado) {
            return [
                'sucesso'      => true,
                'pagamento_id' => 'fake-'.Str::uuid(),
                'copia_cola'   => $this->brCodeFake($compra),
                'qr_base64'    => null, // o app gera o QR a partir do copia-e-cola
                'status'       => 'pendente',
            ];
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->withHeaders(['X-Idempotency-Key' => (string) Str::uuid()])
                ->post($this->baseUrl.'/v1/payments', [
                    'transaction_amount' => (float) $compra->valor,
                    'description'        => 'FastPass · '.($compra->excursao->titulo ?? 'Passagem'),
                    'payment_method_id'  => 'pix',
                    'external_reference' => (string) $compra->id,
                    'payer'              => array_filter([
                        'email'      => $user->email,
                        'first_name' => $user->name,
                        'identification' => $user->cpf ? [
                            'type'   => 'CPF',
                            'number' => preg_replace('/\D/', '', $user->cpf),
                        ] : null,
                    ]),
                ]);

            if ($response->failed()) {
                return ['sucesso' => false, 'erro' => $response->json('message') ?? 'Erro ao criar cobrança Pix.'];
            }

            $data = $response->json();
            $tx = $data['point_of_interaction']['transaction_data'] ?? [];

            return [
                'sucesso'      => true,
                'pagamento_id' => (string) ($data['id'] ?? ''),
                'copia_cola'   => $tx['qr_code'] ?? null,
                'qr_base64'    => $tx['qr_code_base64'] ?? null,
                'status'       => $this->normalizar($data['status'] ?? 'pending'),
            ];
        } catch (Throwable $e) {
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Consulta o status de um pagamento.
     *
     * @return array{sucesso:bool, status?:string, erro?:string} status: pendente | aprovado | recusado
     */
    public function consultarStatus(string $pagamentoId): array
    {
        if ($this->modoSimulado) {
            return ['sucesso' => true, 'status' => 'aprovado'];
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->get($this->baseUrl.'/v1/payments/'.$pagamentoId);

            if ($response->failed()) {
                return ['sucesso' => false, 'erro' => 'Pagamento não encontrado.'];
            }

            return ['sucesso' => true, 'status' => $this->normalizar($response->json('status'))];
        } catch (Throwable $e) {
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }

    protected function normalizar(?string $status): string
    {
        return match ($status) {
            'approved'              => 'aprovado',
            'rejected', 'cancelled' => 'recusado',
            default                 => 'pendente',
        };
    }

    // BR Code fictício (NÃO é um Pix válido; serve apenas para a demonstração).
    protected function brCodeFake(Compra $compra): string
    {
        return '00020126FASTPASS-SIMULADO-'.$compra->id.'-'
            .Str::upper(Str::random(10)).'5204000053039865802BR6008FASTPASS6304FAKE';
    }
}
