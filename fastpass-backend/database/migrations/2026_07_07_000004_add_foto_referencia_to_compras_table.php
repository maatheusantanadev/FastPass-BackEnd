<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            // Foto (base64) enviada no cadastro da biometria — guardada para
            // o motorista poder comparar visualmente com a foto do pedido.
            $table->longText('foto_referencia')->nullable()->after('facial_id');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn('foto_referencia');
        });
    }
};
