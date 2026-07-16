<?php

namespace App\Models;

use App\Enums\SopViolationType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An SOP deviation recorded against a sample part (late transfer, cold-chain
 * breach, ...). Recorded for audit; does not block the workflow.
 */
class SopViolation extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'sample_part_id',
        'type',
        'details',
        'detected_at',
        'resolved_at',
        'resolved_by_id',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => SopViolationType::class,
            'details' => 'array',
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<SamplePart, $this> */
    public function samplePart(): BelongsTo
    {
        return $this->belongsTo(SamplePart::class);
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
