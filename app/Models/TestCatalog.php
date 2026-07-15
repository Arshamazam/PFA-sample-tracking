<?php

namespace App\Models;

use App\Enums\LabSection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catalog of tests per food category with parameter templates and routing.
 */
class TestCatalog extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'test_catalog';

    protected $fillable = [
        'food_category',
        'lab_section',
        'test_name',
        'parameters',
        'tat_hours',
    ];

    protected function casts(): array
    {
        return [
            'lab_section' => LabSection::class,
            'parameters' => 'array',
            'tat_hours' => 'integer',
        ];
    }
}
