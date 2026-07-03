<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    protected $fillable = [
        'hotel_id',
        'contact_id',
        'conversation_id',
        'room_id',
        'check_in',
        'check_out',
        'guests',
        'nights',
        'total_amount',
        'currency',
        'payment_method',
        'status',
        'hold_expires_at',
        'confirmed_at',
        'meta_json',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'guests' => 'integer',
        'nights' => 'integer',
        'total_amount' => 'decimal:2',
        'hold_expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'meta_json' => 'array',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }
}
