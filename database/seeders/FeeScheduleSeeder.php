<?php

namespace Database\Seeders;

use App\Fees\Models\FeeClosingSlab;
use App\Fees\Models\FeeCollectionRule;
use App\Fees\Models\FeeCommissionSlab;
use App\Fees\Models\FeeSchedule;
use App\Fees\Models\FeeShippingSlab;
use App\Shared\Enums\FeeScheduleStatus;
use App\Shared\Enums\ShippingZone;
use Illuminate\Database\Seeder;

class FeeScheduleSeeder extends Seeder
{
    public function run(): void
    {
        if (FeeSchedule::count() > 0) {
            return;
        }

        $schedule = FeeSchedule::create([
            'name' => 'Standard Fee Schedule',
            'version' => 1,
            'status' => FeeScheduleStatus::Active,
            'effective_from' => now()->subMonths(6),
            'effective_to' => null,
            'gst_rate' => 18.00,
            'tcs_rate' => 0.50,
            'tds_standard_rate' => 0.10,
            'tds_no_pan_rate' => 5.00,
            'default_weight_grams' => 500,
        ]);

        // Default Commission Slabs (Fallback)
        FeeCommissionSlab::create([
            'fee_schedule_id' => $schedule->id,
            'vertical_id' => null,
            'min_price' => 0,
            'max_price' => 50000, // Up to ₹500
            'rate_bps' => 1200,   // 12.00%
        ]);

        FeeCommissionSlab::create([
            'fee_schedule_id' => $schedule->id,
            'vertical_id' => null,
            'min_price' => 50001,
            'max_price' => 100000, // ₹500.01 to ₹1,000
            'rate_bps' => 1000,    // 10.00%
        ]);

        FeeCommissionSlab::create([
            'fee_schedule_id' => $schedule->id,
            'vertical_id' => null,
            'min_price' => 100001,
            'max_price' => null,  // Above ₹1,000
            'rate_bps' => 800,     // 8.00%
        ]);

        // Closing Fee Slabs
        FeeClosingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_price' => 0,
            'max_price' => 25000, // Up to ₹250
            'fee_amount' => 1000, // ₹10.00
        ]);

        FeeClosingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_price' => 25001,
            'max_price' => 50000, // ₹250.01 to ₹500
            'fee_amount' => 1500, // ₹15.00
        ]);

        FeeClosingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_price' => 50001,
            'max_price' => 100000, // ₹500.01 to ₹1,000
            'fee_amount' => 2000,  // ₹20.00
        ]);

        FeeClosingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_price' => 100001,
            'max_price' => null,   // Above ₹1,000
            'fee_amount' => 3000,  // ₹30.00
        ]);

        // Shipping Fee Slabs
        // Local
        FeeShippingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_weight_grams' => 0,
            'max_weight_grams' => 500,
            'zone' => ShippingZone::Local,
            'fee_amount' => 3000, // ₹30.00
            'is_default' => true,
        ]);
        FeeShippingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_weight_grams' => 501,
            'max_weight_grams' => 1000,
            'zone' => ShippingZone::Local,
            'fee_amount' => 4500, // ₹45.00
            'is_default' => false,
        ]);
        FeeShippingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_weight_grams' => 1001,
            'max_weight_grams' => null,
            'zone' => ShippingZone::Local,
            'fee_amount' => 6000, // ₹60.00
            'is_default' => false,
        ]);

        // Regional
        FeeShippingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_weight_grams' => 0,
            'max_weight_grams' => 500,
            'zone' => ShippingZone::Regional,
            'fee_amount' => 4000, // ₹40.00
            'is_default' => true,
        ]);
        FeeShippingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_weight_grams' => 501,
            'max_weight_grams' => 1000,
            'zone' => ShippingZone::Regional,
            'fee_amount' => 6000, // ₹60.00
            'is_default' => false,
        ]);
        FeeShippingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_weight_grams' => 1001,
            'max_weight_grams' => null,
            'zone' => ShippingZone::Regional,
            'fee_amount' => 8000, // ₹80.00
            'is_default' => false,
        ]);

        // National
        FeeShippingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_weight_grams' => 0,
            'max_weight_grams' => 500,
            'zone' => ShippingZone::National,
            'fee_amount' => 5000, // ₹50.00
            'is_default' => true,
        ]);
        FeeShippingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_weight_grams' => 501,
            'max_weight_grams' => 1000,
            'zone' => ShippingZone::National,
            'fee_amount' => 7500, // ₹75.00
            'is_default' => false,
        ]);
        FeeShippingSlab::create([
            'fee_schedule_id' => $schedule->id,
            'min_weight_grams' => 1001,
            'max_weight_grams' => null,
            'zone' => ShippingZone::National,
            'fee_amount' => 10000, // ₹100.00
            'is_default' => false,
        ]);

        // Collection Rules
        FeeCollectionRule::create([
            'fee_schedule_id' => $schedule->id,
            'payment_method' => 'cod',
            'rate_bps' => 0,
            'fee_amount' => 0,
        ]);
    }
}
