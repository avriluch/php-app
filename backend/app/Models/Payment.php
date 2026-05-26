<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'package_purchase_id',
        'monto',
        'estado',
        'metodo',
        'fecha_pago',
        'referencia_pasarela',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'estado' => PaymentStatus::class,
            'metodo' => PaymentMethod::class,
            'fecha_pago' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function packagePurchase(): BelongsTo
    {
        return $this->belongsTo(PackagePurchase::class);
    }
}
