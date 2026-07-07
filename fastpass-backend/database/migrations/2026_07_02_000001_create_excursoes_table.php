<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excursoes', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('destino');
            $table->dateTime('data_saida');
            $table->dateTime('data_retorno')->nullable();
            $table->decimal('preco', 10, 2);
            $table->unsignedInteger('vagas_total');
            $table->unsignedInteger('vagas_disponiveis');
            $table->string('status', 20)->default('aberta'); // aberta | encerrada | concluida
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excursoes');
    }
};
