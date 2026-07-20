<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FBO dispute against an UNFIT verdict.
 */
class Dispute extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'sampling_event_id',
        'filed_by_name',
        'filed_by_phone',
        'filed_by_cnic',
        'reason',
        'decision_notes',
        'status',
        'source',
        'reference_no',
        'filed_at',
        'decided_by_id',
        'decided_at',
        'retest_lab_result_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'filed_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    // Relationships -------------------------------------------------------

    /** @return BelongsTo<SamplingEvent, $this> */
    public function samplingEvent(): BelongsTo
    {
        return $this->belongsTo(SamplingEvent::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    /** @return BelongsTo<LabResult, $this> */
    public function retestLabResult(): BelongsTo
    {
        return $this->belongsTo(LabResult::class, 'retest_lab_result_id');
    }
}
