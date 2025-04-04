<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Location extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'street_number',
        'street_name',
        'city',
        'state',
        'country',
        'postcode',
        'latitude',
        'longitude',
    ];

    /**
     * Get the user that owns the location.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the full street address.
     *
     * @return string
     */
    public function getFullStreetAttribute(): string
    {
        return "{$this->street_number} {$this->street_name}";
    }
}