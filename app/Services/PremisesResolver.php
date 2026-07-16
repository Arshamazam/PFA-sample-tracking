<?php

namespace App\Services;

use App\Models\Premises;

/**
 * Resolves a premises by its license number.
 *
 * TEMPORARY fallback: if the license is not found in the local cache we auto-create
 * a minimal record with source=MANUAL. This mirrors the interim PFA Warehouse
 * pattern and exists only until integration with PFA's ~400k registered-business
 * database, after which lookups will hit that system (source=PFA_DB) and unknown
 * licenses will be rejected rather than fabricated.
 *
 * @phpstan-param array{name?:string,address?:string,city?:string,owner_name?:string,owner_phone?:string}  $attributes
 */
class PremisesResolver
{
    /**
     * @param  array<string, mixed>  $attributes  optional details to seed a new fallback record
     */
    public function resolveByLicense(string $licenseNo, array $attributes = []): Premises
    {
        $licenseNo = trim($licenseNo);

        $premises = Premises::where('license_no', $licenseNo)->first();
        if ($premises !== null) {
            return $premises;
        }

        return Premises::create([
            'license_no' => $licenseNo,
            'name' => $attributes['name'] ?? 'Unregistered premises ('.$licenseNo.')',
            'address' => $attributes['address'] ?? 'Unknown',
            'city' => $attributes['city'] ?? 'Lahore',
            'owner_name' => $attributes['owner_name'] ?? null,
            'owner_phone' => $attributes['owner_phone'] ?? null,
            'source' => 'MANUAL',
        ]);
    }
}
