<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\Modalidad;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'client_user_id',
        'professional_profile_id',
        'service_id',
        'package_purchase_id',
        'fecha_hora',
        'active_slot',
        'modalidad',
        'estado',
        'url_video_llamada',
        'cancelled_at',
        'cancel_motivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'active_slot' => 'datetime',
            'modalidad' => Modalidad::class,
            'estado' => BookingStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function professionalProfile(): BelongsTo
    {
        return $this->belongsTo(ProfessionalProfile::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function packagePurchase(): BelongsTo
    {
        return $this->belongsTo(PackagePurchase::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
