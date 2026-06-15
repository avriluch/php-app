<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $fillable = [
        'nombre_plataforma',
        'email_soporte',
        'mensaje_mantenimiento',
        'registro_abierto',
        'mantenimiento_activo',
        'recordatorio_horas_antes',
        'antelacion_reserva_min_horas',
    ];

    protected function casts(): array
    {
        return [
            'registro_abierto' => 'boolean',
            'mantenimiento_activo' => 'boolean',
            'recordatorio_horas_antes' => 'integer',
            'antelacion_reserva_min_horas' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'nombre_plataforma' => config('app.name', 'ServiConnect'),
            'registro_abierto' => true,
            'mantenimiento_activo' => false,
            'recordatorio_horas_antes' => 24,
            'antelacion_reserva_min_horas' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    public function toPublicArray(): array
    {
        return [
            'nombre_plataforma' => $this->nombre_plataforma,
            'email_soporte' => $this->email_soporte,
            'registro_abierto' => $this->registro_abierto,
            'mantenimiento_activo' => $this->mantenimiento_activo,
            'mensaje_mantenimiento' => $this->mensaje_mantenimiento,
            'antelacion_reserva_min_horas' => $this->antelacion_reserva_min_horas,
        ];
    }

    /** @return array<string, mixed> */
    public function toAdminArray(): array
    {
        return [
            ...$this->toPublicArray(),
            'recordatorio_horas_antes' => $this->recordatorio_horas_antes,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
