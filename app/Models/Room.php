<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    protected $fillable = [
        'hotel_id',
        'room_type_id',
        'nombre',
        'descripcion',
        'capacidad',
        'inventario_total',
        'weekday_rate',
        'weekend_rate',
        'base_status',
        'activo',
    ];

    protected $casts = [
        'capacidad' => 'integer',
        'inventario_total' => 'integer',
        'weekday_rate' => 'decimal:2',
        'weekend_rate' => 'decimal:2',
        'activo' => 'boolean',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function inventoryDays(): HasMany
    {
        return $this->hasMany(RoomInventoryDay::class);
    }

    public function dayStatuses(): HasMany
    {
        return $this->hasMany(RoomDayStatus::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class, 'entity_id')
            ->where('entity_type', 'room');
    }
}
