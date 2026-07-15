<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Named atomic counter, keyed by a string. Backs the event-code generator.
 */
class SequenceCounter extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }
}
