<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    protected $fillable = [
        'reservation_id',
        'provider',
        'provider_ref',
        'status',
        'amount',
        'currency',
        'payment_url',
        'payload_json',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload_json' => 'array',
        'paid_at' => 'datetime',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}
