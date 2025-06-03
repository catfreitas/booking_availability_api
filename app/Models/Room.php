<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'external_room_id',
        'name',
    ];

    /**
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    /**
     * Get the property that owns the room
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

     /**
     * Get the availabilities for the room.
     */
    public function  roomAvailability(): HasMany
    {
        return $this->hasMany(RoomAvailability::class);
    }
}
