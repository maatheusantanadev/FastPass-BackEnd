<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_embarque', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->longText('foto_enviada'); // selfie enviada pelo passageiro (base64)
            $table->string('status', 20)->default('pendente'); // pendente | aprovado | reprovado
            $table->foreignId('resolvido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolvido_em')->nullable();
            $table->timestamps();

            $table->index(['compra_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_embarque');
    }
};
