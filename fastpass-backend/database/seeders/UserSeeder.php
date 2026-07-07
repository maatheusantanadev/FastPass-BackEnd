<?php

namespace Database\Seeders;

use App\Models\Compra;
use App\Models\Excursao;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Cria as contas fixas de motorista e administrador (mesmas credenciais
     * que antes viviam hardcoded no front-end), atribui o motorista a uma
     * viagem e garante alguns passageiros/compras de exemplo — sem isso, as
     * telas do motorista (viagens/lista de embarque) ficam vazias por não
     * haver nenhuma compra real no banco.
     */
    public function run(): void
    {
        $motorista = User::updateOrCreate(
            ['email' => 'motorista@fastpass.com'],
            [
                'name'     => 'Sr. Antônio',
                'password' => 'motorista123',
                'role'     => User::ROLE_MOTORISTA,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin@fastpass.com'],
            [
                'name'     => 'Administrador',
                'password' => 'admin123',
                'role'     => User::ROLE_ADMINISTRADOR,
            ]
        );

        // Atribui o motorista fixo à primeira excursão aberta que ainda não
        // tenha motorista (idempotente: não reatribui se já houver uma).
        $excursao = Excursao::where('status', Excursao::STATUS_ABERTA)
            ->where(function ($q) use ($motorista) {
                $q->whereNull('motorista_id')->orWhere('motorista_id', $motorista->id);
            })
            ->orderBy('data_saida')
            ->first();

        if (! $excursao) {
            return;
        }

        $excursao->update(['motorista_id' => $motorista->id]);

        // Passageiros + compras confirmadas de exemplo, para a lista de
        // embarque do motorista não aparecer vazia na demonstração.
        $passageiros = [
            ['name' => 'Maria Andrade', 'email' => 'maria.andrade@exemplo.com', 'cpf' => '12345678901'],
            ['name' => 'João Nascimento', 'email' => 'joao.nascimento@exemplo.com', 'cpf' => '23456789012'],
            ['name' => 'Ana Beatriz Lima', 'email' => 'ana.lima@exemplo.com', 'cpf' => '34567890123'],
        ];

        foreach ($passageiros as $dados) {
            $passageiro = User::updateOrCreate(
                ['email' => $dados['email']],
                [
                    'name'     => $dados['name'],
                    'password' => 'passageiro123',
                    'cpf'      => $dados['cpf'],
                    'role'     => User::ROLE_PASSAGEIRO,
                ]
            );

            Compra::firstOrCreate(
                ['user_id' => $passageiro->id, 'excursao_id' => $excursao->id],
                [
                    'codigo_qr' => (string) Str::uuid(),
                    'valor'     => $excursao->preco,
                    'status'    => Compra::STATUS_CONFIRMADA,
                ]
            );
        }
    }
}
