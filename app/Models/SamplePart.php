<?php

namespace App\Models;

use App\Enums\PartRole;
use App\Enums\PartStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One physical part of a sampling event (LAB / REFERENCE / FBO_COPY). Its `status`
 * is the denormalized current PartStatus; the authoritative trail is custody_events.
 */
class SamplePart extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'sampling_event_id',
        'role',
        'qr_token',
        'blind_code',
        'seal_number',
        'seal_photo_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'role' => PartRole::class,
            'status' => PartStatus::class,
        ];
    }

    // Relationships -------------------------------------------------------

    /** @return BelongsTo<SamplingEvent, $this> */
    public function samplingEvent(): BelongsTo
    {
        return $this->belongsTo(SamplingEvent::class);
    }

    /** @return HasMany<CustodyEvent, $this> */
    public function custodyEvents(): HasMany
    {
        return $this->hasMany(CustodyEvent::class);
    }

    /** @return HasOne<LabResult, $this> */
    public function labResult(): HasOne
    {
        return $this->hasOne(LabResult::class);
    }

    /** @return HasMany<SopViolation, $this> */
    public function sopViolations(): HasMany
    {
        return $this->hasMany(SopViolation::class);
    }
}
