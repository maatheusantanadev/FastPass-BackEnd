<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            // facial | qrcode | manual — preenchido quando o embarque é confirmado
            $table->string('metodo_embarque', 20)->nullable()->after('embarcado_em');
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropColumn('metodo_embarque');
        });
    }
};
