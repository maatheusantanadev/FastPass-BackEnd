<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cliente do microserviço FastPass-Facial (FastAPI + DeepFace).
 *
 * O serviço guarda o embedding facial ligado ao `fastpass_user_id` (o id do
 * usuário no Laravel) e, no embarque, devolve esse mesmo id. A comunicação é
 * server-to-server, autenticada por Bearer (FACIAL_API_KEY) e com a imagem
 * enviada como arquivo (multipart), conforme o contrato do serviço:
 *
 *   POST /faces     (fastpass_user_id, nome?, file) -> { facial_id }
 *   POST /identify  (file, candidatos?)             -> { match, fastpass_user_id, confianca }
 *
 * A URL base é FACIAL_API_URL. Para demonstrações sem o serviço no ar, defina
 * FACIAL_API_FAKE=true.
 */
class FacialRecognitionService
{
    protected ?string $baseUrl;
    protected ?string $apiKey;
    protected bool $modoSimulado;

    public function __construct()
    {
        $this->baseUrl      = rtrim((string) config('services.facial.url'), '/') ?: null;
        $this->apiKey       = config('services.facial.key');
        $this->modoSimulado = (bool) config('services.facial.fake');
    }

    /**
     * Cadastra (ou substitui) o rosto de um usuário no serviço facial.
     *
     * @return array{sucesso: bool, facial_id?: string, erro?: string}
     */
    public function registrar(int $userId, string $imagemBase64, ?string $nome = null): array
    {
        if ($this->modoSimulado) {
            return ['sucesso' => true, 'facial_id' => 'fake-'.Str::uuid()];
        }

        if (! $this->baseUrl) {
            return ['sucesso' => false, 'erro' => 'FACIAL_API_URL não configurada.'];
        }

        $binario = $this->decodificar($imagemBase64);
        if ($binario === null) {
            return ['sucesso' => false, 'erro' => 'Imagem inválida.'];
        }

        try {
            $response = Http::timeout(60)
                ->withToken($this->apiKey)
                ->attach('file', $binario, 'face.jpg')
                ->post($this->baseUrl.'/faces', array_filter([
                    'fastpass_user_id' => (string) $userId,
                    'nome'             => $nome,
                ], fn ($v) => $v !== null && $v !== ''));

            if ($response->failed()) {
                return ['sucesso' => false, 'erro' => $response->json('detail') ?? 'Erro no serviço facial.'];
            }

            return [
                'sucesso'   => true,
                'facial_id' => (string) ($response->json('facial_id') ?? $userId),
            ];
        } catch (Throwable $e) {
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Identifica o passageiro de uma imagem capturada no embarque.
     *
     * @param  array<int>  $candidatos  ids dos passageiros a considerar (opcional)
     * @return array{sucesso: bool, user_id?: int, confianca?: float, erro?: string}
     */
    public function verificar(string $imagemBase64, array $candidatos = []): array
    {
        if ($this->modoSimulado) {
            // No modo simulado, aceita "user:<id>" como conteúdo da imagem
            // para permitir testar o fluxo completo sem o serviço no ar.
            if (preg_match('/^user:(\d+)$/', $imagemBase64, $m)) {
                return ['sucesso' => true, 'user_id' => (int) $m[1]];
            }

            return ['sucesso' => false, 'erro' => 'Modo simulado: use "user:<id>" como imagem.'];
        }

        if (! $this->baseUrl) {
            return ['sucesso' => false, 'erro' => 'FACIAL_API_URL não configurada.'];
        }

        $binario = $this->decodificar($imagemBase64);
        if ($binario === null) {
            return ['sucesso' => false, 'erro' => 'Imagem inválida.'];
        }

        try {
            $campos = [];
            if (! empty($candidatos)) {
                $campos['candidatos'] = json_encode(array_values($candidatos));
            }

            $response = Http::timeout(60)
                ->withToken($this->apiKey)
                ->attach('file', $binario, 'face.jpg')
                ->post($this->baseUrl.'/identify', $campos);

            if ($response->failed() || ! $response->json('match')) {
                return [
                    'sucesso' => false,
                    'erro'    => $response->json('detail')
                        ?? $response->json('motivo')
                        ?? 'Face não reconhecida.',
                ];
            }

            return [
                'sucesso'   => true,
                'user_id'   => (int) $response->json('fastpass_user_id'),
                'confianca' => $response->json('confianca'),
            ];
        } catch (Throwable $e) {
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }

    /**
     * Decodifica uma imagem em base64 (aceita data URI ou base64 puro).
     */
    protected function decodificar(string $imagem): ?string
    {
        if (str_contains($imagem, ',')) {
            $imagem = substr($imagem, strpos($imagem, ',') + 1);
        }

        $binario = base64_decode($imagem, true);

        return $binario === false ? null : $binario;
    }
}
