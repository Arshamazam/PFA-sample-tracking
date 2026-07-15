<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Food business premises. Local cache / fallback for PFA's registered-business DB.
 */
class Premises extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'premises';

    protected $fillable = [
        'license_no',
        'name',
        'address',
        'city',
        'owner_name',
        'owner_phone',
        'source',
    ];

    // Relationships -------------------------------------------------------

    /** @return HasMany<SamplingEvent, $this> */
    public function samplingEvents(): HasMany
    {
        return $this->hasMany(SamplingEvent::class);
    }

    /** @return HasMany<RapidTest, $this> */
    public function rapidTests(): HasMany
    {
        return $this->hasMany(RapidTest::class);
    }
}
