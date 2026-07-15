<?php

namespace Database\Seeders;

use App\Models\Premises;
use Illuminate\Database\Seeder;

/**
 * Seeds 10 sample Lahore food businesses. Placeholder data for local development;
 * production premises come from PFA's registered-business database (source PFA_DB).
 */
class PremisesSeeder extends Seeder
{
    public function run(): void
    {
        $premises = [
            ['Al-Madina Dairy & Milk Shop', 'Shop 12, Ichhra Bazaar', 'Muhammad Aslam', '0300-4412233'],
            ['Shezan Bakers & Sweets', '45-C, Main Boulevard, Gulberg III', 'Farhan Sheikh', '0321-5566778'],
            ['Fresh Valley Milk Depot', 'Plot 8, Township Sector B', 'Rana Tariq', '0333-2211009'],
            ['Lahore Ghee & Oil Traders', 'Shop 3, Akbari Mandi', 'Haji Yousaf', '0301-9988776'],
            ['Bismillah Spice House', 'Stall 22, Shah Alam Market', 'Abdul Rehman', '0345-1122334'],
            ['Chenab Mineral Water Plant', 'Industrial Estate, Kot Lakhpat', 'Imran Bhatti', '0302-7766554'],
            ['Kausar Meat Shop', 'Shop 5, Anarkali Food Street', 'Nadeem Butt', '0311-3344556'],
            ['Punjab Tandoor & Naan', '78-B, Wahdat Road', 'Ghulam Mustafa', '0322-8899001'],
            ['Nurpur Milk Collection Point', 'Near Thokar Niaz Baig', 'Waqas Ahmed', '0334-6677889'],
            ['Green Leaf Tea & Grocery', 'Shop 19, Liberty Market, Gulberg', 'Sana Ullah', '0300-5544332'],
        ];

        foreach ($premises as $index => [$name, $address, $owner, $phone]) {
            // Realistic-looking PFA license number: PFA-LHR-{year}-{5-digit}.
            $licenseNo = sprintf('PFA-LHR-2025-%05d', 10001 + $index);

            Premises::updateOrCreate(
                ['license_no' => $licenseNo],
                [
                    'name' => $name,
                    'address' => $address,
                    'city' => 'Lahore',
                    'owner_name' => $owner,
                    'owner_phone' => $phone,
                    'source' => 'MANUAL',
                ],
            );
        }
    }
}
