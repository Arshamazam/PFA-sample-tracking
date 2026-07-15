<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Key-value application settings.
 */
class Setting extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Convenience accessor for a setting value with a default fallback.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }
}
