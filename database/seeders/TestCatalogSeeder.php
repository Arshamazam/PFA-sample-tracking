<?php

namespace Database\Seeders;

use App\Enums\LabSection;
use App\Models\TestCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds the test catalog with common food categories and their parameter templates.
 *
 * IMPORTANT: The permissible limits below are PLAUSIBLE PLACEHOLDERS for development.
 * They MUST be confirmed against the official Punjab Food Authority standards /
 * Punjab Pure Food Regulations before any production use.
 */
class TestCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'food_category' => 'MILK',
                'lab_section' => LabSection::CHEMICAL,
                'test_name' => 'Milk Composition & Adulteration',
                'tat_hours' => 48,
                'parameters' => [
                    ['name' => 'Fat', 'unit' => '%', 'permissible_limit' => 'min 3.5'],
                    ['name' => 'Solids-Not-Fat (SNF)', 'unit' => '%', 'permissible_limit' => 'min 8.9'],
                    ['name' => 'Added Water', 'unit' => '%', 'permissible_limit' => 'max 0'],
                    ['name' => 'Starch', 'unit' => 'qualitative', 'permissible_limit' => 'absent'],
                    ['name' => 'Cane Sugar', 'unit' => 'qualitative', 'permissible_limit' => 'absent'],
                    ['name' => 'Detergent', 'unit' => 'qualitative', 'permissible_limit' => 'absent'],
                ],
            ],
            [
                'food_category' => 'MILK',
                'lab_section' => LabSection::MICROBIOLOGY,
                'test_name' => 'Milk Microbiological Quality',
                'tat_hours' => 72,
                'parameters' => [
                    ['name' => 'Total Plate Count (TPC)', 'unit' => 'CFU/ml', 'permissible_limit' => 'max 200000'],
                    ['name' => 'Coliform Count', 'unit' => 'CFU/ml', 'permissible_limit' => 'max 10'],
                ],
            ],
            [
                'food_category' => 'OIL_GHEE',
                'lab_section' => LabSection::FAT_OIL,
                'test_name' => 'Edible Oil & Ghee Quality',
                'tat_hours' => 48,
                'parameters' => [
                    ['name' => 'Free Fatty Acids (FFA)', 'unit' => '% oleic acid', 'permissible_limit' => 'max 0.5'],
                    ['name' => 'Peroxide Value', 'unit' => 'meq O2/kg', 'permissible_limit' => 'max 10'],
                    ['name' => 'Iodine Value', 'unit' => 'g I2/100g', 'permissible_limit' => '85-98'],
                    ['name' => 'Moisture', 'unit' => '%', 'permissible_limit' => 'max 0.2'],
                ],
            ],
            [
                'food_category' => 'WATER',
                'lab_section' => LabSection::MICROBIOLOGY,
                'test_name' => 'Drinking Water Microbiology',
                'tat_hours' => 72,
                'parameters' => [
                    ['name' => 'Total Plate Count (TPC)', 'unit' => 'CFU/ml', 'permissible_limit' => 'max 100'],
                    ['name' => 'Total Coliforms', 'unit' => 'CFU/100ml', 'permissible_limit' => '0'],
                    ['name' => 'E. coli', 'unit' => 'CFU/100ml', 'permissible_limit' => '0'],
                ],
            ],
            [
                'food_category' => 'SPICES',
                'lab_section' => LabSection::CHEMICAL,
                'test_name' => 'Spice Quality & Adulteration',
                'tat_hours' => 48,
                'parameters' => [
                    ['name' => 'Moisture', 'unit' => '%', 'permissible_limit' => 'max 12'],
                    ['name' => 'Artificial Colour', 'unit' => 'qualitative', 'permissible_limit' => 'absent'],
                    ['name' => 'Total Ash', 'unit' => '%', 'permissible_limit' => 'max 8'],
                    ['name' => 'Acid Insoluble Ash', 'unit' => '%', 'permissible_limit' => 'max 1.5'],
                ],
            ],
        ];

        foreach ($catalog as $entry) {
            TestCatalog::updateOrCreate(
                [
                    'food_category' => $entry['food_category'],
                    'test_name' => $entry['test_name'],
                ],
                [
                    'lab_section' => $entry['lab_section'],
                    'parameters' => $entry['parameters'],
                    'tat_hours' => $entry['tat_hours'],
                ],
            );
        }
    }
}
