<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
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
        'facial_registrada',
        'facial_id',
        'foto_referencia',
        'embarcado_em',
        'metodo_embarque',
    ];

    protected function casts(): array
    {
        return [
            'valor'             => 'decimal:2',
            'facial_registrada' => 'boolean',
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

    public function pedidosEmbarque(): HasMany
    {
        return $this->hasMany(PedidoEmbarque::class);
    }
}
