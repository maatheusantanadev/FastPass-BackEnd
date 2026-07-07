<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoEmbarque extends Model
{
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_APROVADO = 'aprovado';
    public const STATUS_REPROVADO = 'reprovado';

    protected $table = 'pedidos_embarque';

    protected $fillable = [
        'compra_id',
        'foto_enviada',
        'status',
        'resolvido_por',
        'resolvido_em',
    ];

    protected function casts(): array
    {
        return [
            'resolvido_em' => 'datetime',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function resolvidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolvido_por');
    }
}
