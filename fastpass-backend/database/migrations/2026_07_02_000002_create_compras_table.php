<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('excursao_id')->constrained('excursoes')->cascadeOnDelete();
            $table->uuid('codigo_qr')->unique();
            $table->decimal('valor', 10, 2);
            $table->string('status', 20)->default('confirmada'); // confirmada | embarcada | concluida | cancelada
            $table->boolean('facial_registrada')->default(false);
            $table->string('facial_id')->nullable();
            $table->string('metodo_embarque', 20)->nullable(); // facial | qr | manual
            $table->timestamp('embarcado_em')->nullable();
            $table->timestamps();

            $table->index(['excursao_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
