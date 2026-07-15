<?php

namespace App\Models;

use App\Enums\PartStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * APPEND-ONLY chain-of-custody event. Once written, a custody event may never be
 * updated or deleted — the model boot hooks below enforce this at the ORM level
 * (the DB has no soft deletes and no update path via $fillable-guarded columns).
 */
class CustodyEvent extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'sample_part_id',
        'status',
        'actor_id',
        'latitude',
        'longitude',
        'location_note',
        'temperature_c',
        'photo_path',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PartStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'temperature_c' => 'decimal:2',
        ];
    }

    /**
     * Enforce append-only semantics: block updates and deletes.
     */
    protected static function booted(): void
    {
        static::updating(function (CustodyEvent $event): void {
            throw new RuntimeException('CustodyEvent records are append-only and cannot be updated.');
        });

        static::deleting(function (CustodyEvent $event): void {
            throw new RuntimeException('CustodyEvent records are append-only and cannot be deleted.');
        });
    }

    // Relationships -------------------------------------------------------

    /** @return BelongsTo<SamplePart, $this> */
    public function samplePart(): BelongsTo
    {
        return $this->belongsTo(SamplePart::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
