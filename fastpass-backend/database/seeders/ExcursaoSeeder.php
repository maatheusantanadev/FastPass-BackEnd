<?php

namespace Database\Seeders;

use App\Models\Excursao;
use Illuminate\Database\Seeder;

class ExcursaoSeeder extends Seeder
{
    public function run(): void
    {
        $excursoes = [
            [
                'titulo'       => 'Excursão Praia do Forte',
                'descricao'    => 'Bate-volta com parada no Projeto Tamar e tempo livre na vila.',
                'destino'      => 'Praia do Forte - BA',
                'data_saida'   => now()->addDays(15)->setTime(6, 0),
                'data_retorno' => now()->addDays(15)->setTime(20, 0),
                'preco'        => 149.90,
                'vagas_total'  => 46,
            ],
            [
                'titulo'       => 'Chapada Diamantina - Fim de Semana',
                'descricao'    => 'Dois dias com trilhas ao Poço do Diabo e Morro do Pai Inácio.',
                'destino'      => 'Lençóis - BA',
                'data_saida'   => now()->addDays(30)->setTime(5, 0),
                'data_retorno' => now()->addDays(32)->setTime(22, 0),
                'preco'        => 489.00,
                'vagas_total'  => 44,
            ],
            [
                'titulo'       => 'Excursão Salvador Histórica',
                'descricao'    => 'City tour pelo Pelourinho, Elevador Lacerda e Mercado Modelo.',
                'destino'      => 'Salvador - BA',
                'data_saida'   => now()->addDays(7)->setTime(7, 0),
                'data_retorno' => now()->addDays(7)->setTime(19, 0),
                'preco'        => 119.90,
                'vagas_total'  => 50,
            ],
        ];

        foreach ($excursoes as $dados) {
            Excursao::firstOrCreate(
                ['titulo' => $dados['titulo']],
                $dados + ['vagas_disponiveis' => $dados['vagas_total'], 'status' => Excursao::STATUS_ABERTA]
            );
        }
    }
}
