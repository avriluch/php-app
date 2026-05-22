<?php

namespace App\Models;

use App\Enums\Modalidad;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'professional_profile_id',
        'type',
        'nombre',
        'descripcion',
        'duracion',
        'precio',
        'modalidad',
        'location_id',
        'cantidad_sesiones',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'modalidad' => Modalidad::class,
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function professionalProfile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function packagePurchases(): HasMany
    {
        return $this->hasMany(PackagePurchase::class);
    }

    public function isPackage(): bool
    {
        return $this->type === ServiceType::Package;
    }
}
