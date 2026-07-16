<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A legal sampling event producing exactly three sample parts (the "Rule of Three").
 */
class SamplingEvent extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'event_code',
        'premises_id',
        'fso_id',
        'food_item',
        'food_category',
        'brand_name',
        'is_perishable',
        'witness_name',
        'witness_cnic',
        'witness_signature_path',
        'collected_at',
        'finalized_at',
        'stale_flagged_at',
    ];

    protected function casts(): array
    {
        return [
            'is_perishable' => 'boolean',
            'collected_at' => 'datetime',
            'finalized_at' => 'datetime',
            'stale_flagged_at' => 'datetime',
        ];
    }

    /**
     * A draft event is one that has not yet satisfied the Rule of Three.
     */
    public function isDraft(): bool
    {
        return $this->finalized_at === null;
    }

    // Relationships -------------------------------------------------------

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

    /** @return HasMany<SamplePart, $this> */
    public function parts(): HasMany
    {
        return $this->hasMany(SamplePart::class);
    }

    /** @return HasMany<RapidTest, $this> */
    public function rapidTests(): HasMany
    {
        return $this->hasMany(RapidTest::class);
    }

    /** @return HasMany<Dispute, $this> */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }
}
