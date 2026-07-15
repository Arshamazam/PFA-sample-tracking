<?php

namespace App\Models;

use App\Enums\RapidTestDevice;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * On-site rapid screening test.
 */
class RapidTest extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'sampling_event_id',
        'premises_id',
        'fso_id',
        'device',
        'reading',
        'passed',
        'photo_path',
        'tested_at',
    ];

    protected function casts(): array
    {
        return [
            'device' => RapidTestDevice::class,
            'passed' => 'boolean',
            'tested_at' => 'datetime',
        ];
    }

    // Relationships -------------------------------------------------------

    /** @return BelongsTo<SamplingEvent, $this> */
    public function samplingEvent(): BelongsTo
    {
        return $this->belongsTo(SamplingEvent::class);
    }

    /** @return BelongsTo<Premises, $this> */
    public function premises(): BelongsTo
    {
        return $this->belongsTo(Premises::class);
    }

    /** @return BelongsTo<User, $this> */
    public function fso(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fso_id');
    }
}
