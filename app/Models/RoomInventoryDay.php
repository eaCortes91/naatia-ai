<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomInventoryDay extends Model
{
    protected $fillable = [
        'hotel_id',
        'room_id',
        'fecha',
        'total_units',
        'reserved_units',
        'blocked_units',
        'note',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total_units' => 'integer',
        'reserved_units' => 'integer',
        'blocked_units' => 'integer',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
