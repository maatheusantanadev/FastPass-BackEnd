<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Excursao extends Model
{
    public const STATUS_ABERTA    = 'aberta';
    public const STATUS_ENCERRADA = 'encerrada';
    public const STATUS_CONCLUIDA = 'concluida';

    protected $table = 'excursoes';

    protected $fillable = [
        'titulo',
        'descricao',
        'destino',
        'data_saida',
        'data_retorno',
        'preco',
        'vagas_total',
        'vagas_disponiveis',
        'status',
        'motorista_id',
    ];

    protected function casts(): array
    {
        return [
            'data_saida'   => 'datetime',
            'data_retorno' => 'datetime',
            'preco'        => 'decimal:2',
        ];
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    public function motorista(): BelongsTo
    {
        return $this->belongsTo(User::class, 'motorista_id');
    }

    // Excursões (não concluídas) atribuídas a um motorista, mais próximas primeiro.
    public function scopeDoMotorista($query, int $motoristaId)
    {
        return $query->where('motorista_id', $motoristaId)
            ->where('status', '!=', self::STATUS_CONCLUIDA)
            ->orderBy('data_saida');
    }

    public function scopeDisponiveis($query)
    {
        return $query->where('status', self::STATUS_ABERTA)
            ->where('vagas_disponiveis', '>', 0)
            ->where('data_saida', '>', now())
            ->orderBy('data_saida');
    }
}
