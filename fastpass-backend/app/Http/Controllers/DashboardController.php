<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\Excursao;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    private const ATIVAS = [
        Compra::STATUS_CONFIRMADA,
        Compra::STATUS_EMBARCADA,
        Compra::STATUS_CONCLUIDA,
    ];

    private const MESES = [
        1 => 'jan', 'fev', 'mar', 'abr', 'mai', 'jun',
        'jul', 'ago', 'set', 'out', 'nov', 'dez',
    ];

    private const DIAS = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    private const METODO_ROTULO = [
        'facial' => 'Face ID',
        'qr'     => 'QR Code',
        'manual' => 'Manual',
    ];

    /**
     * Métricas agregadas para o painel da empresa (visão geral + relatórios).
     */
    public function index(): JsonResponse
    {
        $atual = Excursao::disponiveis()->first();

        return response()->json([
            'visao_geral'       => $this->visaoGeral($atual),
            'vendas_por_dia'    => $this->vendasPorDia(),
            'mix_metodos'       => $this->mixMetodos(),
            'relatorios'        => $this->relatorios(),
            'historico_viagens' => $this->historicoViagens(),
        ]);
    }

    private function visaoGeral(?Excursao $atual): array
    {
        $confirmados = $atual
            ? $atual->compras()->whereIn('status', self::ATIVAS)->count()
            : 0;

        $pagamentosHoje = (float) Compra::whereDate('created_at', today())
            ->whereIn('status', self::ATIVAS)
            ->sum('valor');

        return [
            'vagas_ocupadas' => $atual ? $atual->vagas_total - $atual->vagas_disponiveis : 0,
            'capacidade'     => $atual?->vagas_total ?? 0,
            'confirmados'    => $confirmados,
            'pagamentos'     => $pagamentosHoje,
            'ocupacao_media' => $this->ocupacaoMedia(),
            'excursao_atual' => $atual?->destino,
        ];
    }

    private function ocupacaoMedia(): float
    {
        $abertas = Excursao::where('status', Excursao::STATUS_ABERTA)
            ->where('vagas_total', '>', 0)
            ->get();

        if ($abertas->isEmpty()) {
            return 0;
        }

        return round(
            $abertas->avg(fn ($e) => 1 - $e->vagas_disponiveis / $e->vagas_total),
            2
        );
    }

    private function vendasPorDia(): array
    {
        $vendas = [];
        for ($i = 6; $i >= 0; $i--) {
            $dia = today()->subDays($i);
            $vendas[] = [
                'dia'   => self::DIAS[$dia->dayOfWeek],
                'valor' => Compra::whereDate('created_at', $dia)->count(),
            ];
        }

        return $vendas;
    }

    private function mixMetodos(): array
    {
        return Compra::whereNotNull('metodo_embarque')
            ->selectRaw('metodo_embarque, count(*) as total')
            ->groupBy('metodo_embarque')
            ->get()
            ->map(fn ($r) => [
                'metodo' => $r->metodo_embarque,
                'rotulo' => self::METODO_ROTULO[$r->metodo_embarque] ?? ucfirst($r->metodo_embarque),
                'valor'  => (int) $r->total,
            ])
            ->values()
            ->all();
    }

    private function relatorios(): array
    {
        $historico = collect($this->historicoViagens());

        return [
            'presenca_media' => $historico->isNotEmpty() ? round($historico->avg('presenca'), 2) : 0,
            'atrasos'        => 0, // não rastreado no MVP
            'ocupacao'       => $this->ocupacaoMedia(),
            'viagens_mes'    => Excursao::whereMonth('data_saida', now()->month)
                ->whereYear('data_saida', now()->year)
                ->count(),
        ];
    }

    private function historicoViagens(): array
    {
        return Excursao::where('status', Excursao::STATUS_CONCLUIDA)
            ->orderByDesc('data_saida')
            ->get()
            ->map(function (Excursao $e) {
                $confirmados = $e->compras()->whereIn('status', self::ATIVAS)->count();
                $embarcados = $e->compras()
                    ->whereIn('status', [Compra::STATUS_EMBARCADA, Compra::STATUS_CONCLUIDA])
                    ->count();
                $receita = (float) $e->compras()->whereIn('status', self::ATIVAS)->sum('valor');

                return [
                    'id'       => (string) $e->id,
                    'destino'  => $e->destino,
                    'data'     => $e->data_saida
                        ? $e->data_saida->format('d').' '.self::MESES[$e->data_saida->month]
                        : null,
                    'ocupacao' => $e->vagas_total ? round($confirmados / $e->vagas_total, 2) : 0,
                    'presenca' => $confirmados ? round($embarcados / $confirmados, 2) : 0,
                    'receita'  => $receita,
                ];
            })
            ->all();
    }
}
