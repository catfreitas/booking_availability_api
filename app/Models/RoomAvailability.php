<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAvailability extends Model
{
    use HasFactory;

    protected $table = 'room_availabilities';

    protected $fillable = [
        'room_id',
        'date',
        'price',
        'max_guests',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'price' => 'decimal:2',
        'max_guests' => 'integer',
    ];

    /**
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];
    

    /**
     * Get the room that this availability belongs to.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
