<?php

namespace Database\Seeders;

use App\Models\Excursao;
use Illuminate\Database\Seeder;

class ExcursaoSeeder extends Seeder
{
    /**
     * Catálogo inicial de excursões da Bahia.
     *
     * Espelha as excursões apresentadas no app (front-end), de modo que a
     * API entregue exatamente os destinos, operadoras e cenas esperados
     * pelas telas de exploração e detalhe.
     */
    public function run(): void
    {
        $excursoes = [
            [
                'titulo'        => 'Praia do Forte',
                'destino'       => 'Praia do Forte',
                'categoria'     => 'praia',
                'cena'          => 'praia',
                'empresa'       => 'Bahia Sol Turismo',
                'ponto_partida' => 'Terminal da França',
                'ponto_retorno' => 'Praia do Forte',
                'descricao'     => 'Dia inteiro na Praia do Forte com parada no Projeto Tamar, tempo livre na vila e almoço à beira-mar. Ônibus leito com ar-condicionado e guia local.',
                'preco'         => 180.00,
                'vagas_total'   => 33,
                'vagas_disponiveis' => 12,
                'data_saida'    => now()->addDays(10)->setTime(6, 30),
                'data_retorno'  => now()->addDays(10)->setTime(19, 0),
            ],
            [
                'titulo'        => 'Chapada Diamantina',
                'destino'       => 'Chapada Diamantina',
                'categoria'     => 'aventura',
                'cena'          => 'montanha',
                'empresa'       => 'Trilha Viva Expedições',
                'ponto_partida' => 'Terminal da França',
                'ponto_retorno' => 'Lençóis',
                'descricao'     => 'Fim de semana em Lençóis com trilha ao Poço Azul, Cachoeira da Fumaça e pôr do sol no Morro do Pai Inácio. Hospedagem e café da manhã inclusos.',
                'preco'         => 640.00,
                'vagas_total'   => 33,
                'vagas_disponiveis' => 4,
                'data_saida'    => now()->addDays(17)->setTime(5, 0),
                'data_retorno'  => now()->addDays(18)->setTime(21, 0),
            ],
            [
                'titulo'        => 'Morro de São Paulo',
                'destino'       => 'Morro de São Paulo',
                'categoria'     => 'praia',
                'cena'          => 'ilha',
                'empresa'       => 'Bahia Sol Turismo',
                'ponto_partida' => 'Terminal da França',
                'ponto_retorno' => 'Valença',
                'descricao'     => 'Bate-volta à ilha com travessia de catamarã, tempo livre nas quatro praias e volta ao entardecer. Inclui traslado terrestre e marítimo.',
                'preco'         => 290.00,
                'vagas_total'   => 40,
                'vagas_disponiveis' => 20,
                'data_saida'    => now()->addDays(24)->setTime(6, 0),
                'data_retorno'  => now()->addDays(24)->setTime(20, 30),
            ],
            [
                'titulo'        => 'Cachoeira & Recôncavo',
                'destino'       => 'Cachoeira & Recôncavo',
                'categoria'     => 'aventura',
                'cena'          => 'montanha',
                'empresa'       => 'Raízes do Recôncavo',
                'ponto_partida' => 'Terminal da França',
                'ponto_retorno' => 'Cachoeira',
                'descricao'     => 'Circuito histórico por Cachoeira e São Félix com degustação em alambique, centro histórico e travessia da ponte Dom Pedro II.',
                'preco'         => 155.00,
                'vagas_total'   => 33,
                'vagas_disponiveis' => 8,
                'data_saida'    => now()->addDays(31)->setTime(7, 0),
                'data_retorno'  => now()->addDays(31)->setTime(19, 30),
            ],
            [
                'titulo'        => 'Ilha dos Frades',
                'destino'       => 'Ilha dos Frades',
                'categoria'     => 'praia',
                'cena'          => 'ilha',
                'empresa'       => 'Baía Azul Passeios',
                'ponto_partida' => 'Terminal Náutico',
                'ponto_retorno' => 'Ilha dos Frades',
                'descricao'     => 'Passeio de escuna pela Baía de Todos-os-Santos com parada na Ilha dos Frades e Ponta de Nossa Senhora. Almoço opcional na ilha.',
                'preco'         => 145.00,
                'vagas_total'   => 30,
                'vagas_disponiveis' => 15,
                'data_saida'    => now()->addDays(38)->setTime(8, 0),
                'data_retorno'  => now()->addDays(38)->setTime(18, 0),
            ],
        ];

        foreach ($excursoes as $dados) {
            Excursao::firstOrCreate(
                ['titulo' => $dados['titulo']],
                $dados + ['status' => Excursao::STATUS_ABERTA]
            );
        }
    }
}
