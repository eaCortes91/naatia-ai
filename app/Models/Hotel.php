<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hotel extends Model
{
    protected $fillable = [
        'nombre',
        'telefono',
        'email',
        'address_line',
        'neighborhood',
        'city',
        'state',
        'postal_code',
        'latitude',
        'longitude',
        'check_in_time',
        'check_out_time',
        'pet_friendly',
        'amenities_text',
        'policies_text',
        'prompt_base',
        'saludo_base',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'pet_friendly' => 'boolean',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(Rate::class);
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(Faq::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function roomInventoryDays(): HasMany
    {
        return $this->hasMany(RoomInventoryDay::class);
    }

    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    public function roomDayStatuses(): HasMany
    {
        return $this->hasMany(RoomDayStatus::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(HotelService::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(HotelPackage::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }
}
