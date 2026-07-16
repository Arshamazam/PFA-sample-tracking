<?php

namespace App\Models;

use App\Enums\LabSection;
use App\Enums\Verdict;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Analytical result for a single sample part.
 */
class LabResult extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'sample_part_id',
        'lab_section',
        'analyst_id',
        'verified_by_id',
        'parameters',
        'lab_result_revisions',
        'verdict',
        'verdict_at',
        'report_pdf_path',
        'report_photo_path',
    ];

    protected function casts(): array
    {
        return [
            'lab_section' => LabSection::class,
            'verdict' => Verdict::class,
            'parameters' => 'array',
            'lab_result_revisions' => 'array',
            'verdict_at' => 'datetime',
        ];
    }

    // Relationships -------------------------------------------------------

    /** @return BelongsTo<SamplePart, $this> */
    public function samplePart(): BelongsTo
    {
        return $this->belongsTo(SamplePart::class);
    }

    /** @return BelongsTo<User, $this> */
    public function analyst(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analyst_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    /**
     * The dispute retest this result belongs to, if any.
     *
     * @return HasOne<Dispute, $this>
     */
    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class, 'retest_lab_result_id');
    }
}
