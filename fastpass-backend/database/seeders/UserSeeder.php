<?php

namespace Database\Seeders;

use App\Models\Excursao;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Cria as contas fixas de motorista e administrador (mesmas credenciais
     * que antes viviam hardcoded no front-end). Idempotente: pode rodar
     * várias vezes sem duplicar.
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

        // Atribui o motorista fixo à primeira excursão aberta, para que o
        // fluxo de embarque já tenha uma viagem pronta para operar.
        Excursao::where('status', Excursao::STATUS_ABERTA)
            ->whereNull('motorista_id')
            ->orderBy('data_saida')
            ->limit(1)
            ->update(['motorista_id' => $motorista->id]);
    }
}
