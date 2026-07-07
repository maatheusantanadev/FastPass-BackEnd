<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cliente HTTP da API externa de reconhecimento facial (FastAPI + DeepFace).
 *
 * A URL base é definida em FACIAL_API_URL no .env. Para demonstrações
 * sem a API no ar, defina FACIAL_API_FAKE=true para simular as respostas.
 */
class FacialRecognitionService
{
    protected ?string $baseUrl;
    protected bool $modoSimulado;

    public function __construct()
    {
        $this->baseUrl      = rtrim((string) config('services.facial.url'), '/') ?: null;
        $this->modoSimulado = (bool) config('services.facial.fake');
    }

    /**
     * Registra a biometria de um usuário na API de reconhecimento.
     *
     * @return array{sucesso: bool, facial_id?: string, erro?: string}
     */
    public function registrar(int $userId, string $imagemBase64): array
    {
        if ($this->modoSimulado) {
            return ['sucesso' => true, 'facial_id' => 'fake-'.Str::uuid()];
        }

        if (! $this->baseUrl) {
            return ['sucesso' => false, 'erro' => 'FACIAL_API_URL não configurada.'];
        }

        try {
            $response = Http::timeout(30)->post($this->baseUrl.'/register', [
                'user_id' => (string) $userId,
                'image'   => $imagemBase64,
            ]);

            if ($response->failed()) {
                return ['sucesso' => false, 'erro' => $response->json('detail') ?? 'Erro na API facial.'];
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
     * Verifica uma imagem capturada no embarque e identifica o passageiro.
     *
     * @return array{sucesso: bool, user_id?: int, erro?: string}
     */
    public function verificar(string $imagemBase64): array
    {
        if ($this->modoSimulado) {
            // No modo simulado, aceita "user:<id>" como conteúdo da imagem
            // para permitir testar o fluxo completo sem a API no ar.
            if (preg_match('/^user:(\d+)$/', $imagemBase64, $m)) {
                return ['sucesso' => true, 'user_id' => (int) $m[1]];
            }

            return ['sucesso' => false, 'erro' => 'Modo simulado: use "user:<id>" como imagem.'];
        }

        if (! $this->baseUrl) {
            return ['sucesso' => false, 'erro' => 'FACIAL_API_URL não configurada.'];
        }

        try {
            $response = Http::timeout(30)->post($this->baseUrl.'/verify', [
                'image' => $imagemBase64,
            ]);

            if ($response->failed() || ! $response->json('match')) {
                return ['sucesso' => false, 'erro' => $response->json('detail') ?? 'Face não reconhecida.'];
            }

            return [
                'sucesso' => true,
                'user_id' => (int) $response->json('user_id'),
            ];
        } catch (Throwable $e) {
            return ['sucesso' => false, 'erro' => $e->getMessage()];
        }
    }
}
