<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agenda extends Model
{
    protected $fillable = [
        'professional_profile_id',
        'horario_inicio',
        'horario_fin',
        'dias_disponibles',
        'buffer_minutos',
    ];

    protected function casts(): array
    {
        return [
            'dias_disponibles' => 'array',
        ];
    }

    public function professionalProfile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(AgendaException::class);
    }
}
