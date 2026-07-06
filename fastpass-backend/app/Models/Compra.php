<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Compra extends Model
{
    public const STATUS_PENDENTE   = 'pendente_pagamento';
    public const STATUS_CONFIRMADA = 'confirmada';
    public const STATUS_EMBARCADA  = 'embarcada';
    public const STATUS_CONCLUIDA  = 'concluida';
    public const STATUS_CANCELADA  = 'cancelada';

    protected $table = 'compras';

    protected $fillable = [
        'user_id',
        'excursao_id',
        'codigo_qr',
        'valor',
        'status',
        'pagamento_id',
        'pix_copia_cola',
        'pago_em',
        'facial_registrada',
        'facial_id',
        'metodo_embarque',
        'embarcado_em',
    ];

    protected function casts(): array
    {
        return [
            'valor'             => 'decimal:2',
            'facial_registrada' => 'boolean',
            'pago_em'           => 'datetime',
            'embarcado_em'      => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function excursao(): BelongsTo
    {
        return $this->belongsTo(Excursao::class);
    }

    /**
     * Confirma o pagamento: debita a vaga (com lock) e marca a compra como
     * confirmada. Idempotente — só age sobre compras pendentes.
     */
    public function confirmarPagamento(): void
    {
        DB::transaction(function () {
            $compra = static::lockForUpdate()->find($this->id);
            if (! $compra || $compra->status !== self::STATUS_PENDENTE) {
                return;
            }

            $excursao = Excursao::lockForUpdate()->find($compra->excursao_id);
            if ($excursao && $excursao->vagas_disponiveis > 0) {
                $excursao->decrement('vagas_disponiveis');
            }

            $compra->update([
                'status'  => self::STATUS_CONFIRMADA,
                'pago_em' => now(),
            ]);
        });
    }
}
