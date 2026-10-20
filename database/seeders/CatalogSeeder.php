<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Enums\BrandStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Vertical;
use App\Models\VerticalAttribute;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Generic Brand (Approved by default)
        Brand::firstOrCreate(
            ['slug' => 'generic'],
            [
                'name' => 'Generic',
                'status' => BrandStatus::Approved,
            ]
        );

        // 2. Tree 1: Fashion › Men › Topwear › T-Shirt
        $fashion = Category::firstOrCreate(
            ['slug' => 'fashion'],
            ['name' => 'Fashion', 'sort_order' => 1, 'is_active' => true]
        );

        $men = Category::firstOrCreate(
            ['slug' => 'men', 'parent_id' => $fashion->id],
            ['name' => 'Men', 'sort_order' => 1, 'is_active' => true]
        );

        $topwear = Category::firstOrCreate(
            ['slug' => 'topwear', 'parent_id' => $men->id],
            ['name' => 'Topwear', 'sort_order' => 1, 'is_active' => true]
        );

        $tshirt = Vertical::firstOrCreate(
            ['slug' => 't-shirt'],
            [
                'category_id' => $topwear->id,
                'name' => 'T-Shirt',
                'hsn_code' => '6109',
                'gst_rate' => 5.00,
                'is_active' => true,
            ]
        );

        // Attributes for T-Shirt
        VerticalAttribute::firstOrCreate(
            ['vertical_id' => $tshirt->id, 'code' => 'size'],
            [
                'name' => 'Size',
                'type' => AttributeType::Select,
                'is_variant' => true,
                'is_required' => true,
                'sort_order' => 1,
                'options' => [
                    ['label' => 'S', 'value' => 's', 'is_active' => true],
                    ['label' => 'M', 'value' => 'm', 'is_active' => true],
                    ['label' => 'L', 'value' => 'l', 'is_active' => true],
                    ['label' => 'XL', 'value' => 'xl', 'is_active' => true],
                    ['label' => 'XXL', 'value' => 'xxl', 'is_active' => true],
                ],
            ]
        );

        VerticalAttribute::firstOrCreate(
            ['vertical_id' => $tshirt->id, 'code' => 'color'],
            [
                'name' => 'Color',
                'type' => AttributeType::Select,
                'is_variant' => true,
                'is_required' => true,
                'sort_order' => 2,
                'options' => [
                    ['label' => 'Black', 'value' => 'black', 'is_active' => true],
                    ['label' => 'White', 'value' => 'white', 'is_active' => true],
                    ['label' => 'Navy Blue', 'value' => 'navy-blue', 'is_active' => true],
                    ['label' => 'Red', 'value' => 'red', 'is_active' => true],
                ],
            ]
        );

        VerticalAttribute::firstOrCreate(
            ['vertical_id' => $tshirt->id, 'code' => 'fabric'],
            [
                'name' => 'Fabric',
                'type' => AttributeType::Select,
                'is_variant' => false,
                'is_required' => true,
                'sort_order' => 3,
                'options' => [
                    ['label' => 'Cotton', 'value' => 'cotton', 'is_active' => true],
                    ['label' => 'Polyester', 'value' => 'polyester', 'is_active' => true],
                    ['label' => 'Cotton Blend', 'value' => 'cotton-blend', 'is_active' => true],
                ],
            ]
        );

        VerticalAttribute::firstOrCreate(
            ['vertical_id' => $tshirt->id, 'code' => 'sleeve'],
            [
                'name' => 'Sleeve',
                'type' => AttributeType::Select,
                'is_variant' => false,
                'is_required' => false,
                'sort_order' => 4,
                'options' => [
                    ['label' => 'Half Sleeve', 'value' => 'half-sleeve', 'is_active' => true],
                    ['label' => 'Full Sleeve', 'value' => 'full-sleeve', 'is_active' => true],
                    ['label' => 'Sleeveless', 'value' => 'sleeveless', 'is_active' => true],
                ],
            ]
        );

        // 3. Tree 2: Electronics › Mobiles
        $electronics = Category::firstOrCreate(
            ['slug' => 'electronics'],
            ['name' => 'Electronics', 'sort_order' => 2, 'is_active' => true]
        );

        $mobiles = Category::firstOrCreate(
            ['slug' => 'mobiles', 'parent_id' => $electronics->id],
            ['name' => 'Mobiles', 'sort_order' => 1, 'is_active' => true]
        );

        $smartphone = Vertical::firstOrCreate(
            ['slug' => 'smartphone'],
            [
                'category_id' => $mobiles->id,
                'name' => 'Smartphone',
                'hsn_code' => '8517',
                'gst_rate' => 18.00,
                'is_active' => true,
            ]
        );

        // Attributes for Smartphone
        VerticalAttribute::firstOrCreate(
            ['vertical_id' => $smartphone->id, 'code' => 'ram'],
            [
                'name' => 'RAM',
                'type' => AttributeType::Select,
                'unit' => 'GB',
                'is_variant' => true,
                'is_required' => true,
                'sort_order' => 1,
                'options' => [
                    ['label' => '4 GB', 'value' => '4gb', 'is_active' => true],
                    ['label' => '6 GB', 'value' => '6gb', 'is_active' => true],
                    ['label' => '8 GB', 'value' => '8gb', 'is_active' => true],
                    ['label' => '12 GB', 'value' => '12gb', 'is_active' => true],
                ],
            ]
        );

        VerticalAttribute::firstOrCreate(
            ['vertical_id' => $smartphone->id, 'code' => 'storage'],
            [
                'name' => 'Storage',
                'type' => AttributeType::Select,
                'unit' => 'GB',
                'is_variant' => true,
                'is_required' => true,
                'sort_order' => 2,
                'options' => [
                    ['label' => '64 GB', 'value' => '64gb', 'is_active' => true],
                    ['label' => '128 GB', 'value' => '128gb', 'is_active' => true],
                    ['label' => '256 GB', 'value' => '256gb', 'is_active' => true],
                    ['label' => '512 GB', 'value' => '512gb', 'is_active' => true],
                ],
            ]
        );

        VerticalAttribute::firstOrCreate(
            ['vertical_id' => $smartphone->id, 'code' => 'color'],
            [
                'name' => 'Color',
                'type' => AttributeType::Select,
                'is_variant' => true,
                'is_required' => true,
                'sort_order' => 3,
                'options' => [
                    ['label' => 'Midnight Black', 'value' => 'midnight-black', 'is_active' => true],
                    ['label' => 'Ocean Blue', 'value' => 'ocean-blue', 'is_active' => true],
                    ['label' => 'Starlight', 'value' => 'starlight', 'is_active' => true],
                ],
            ]
        );

        VerticalAttribute::firstOrCreate(
            ['vertical_id' => $smartphone->id, 'code' => 'battery_capacity'],
            [
                'name' => 'Battery Capacity',
                'type' => AttributeType::Number,
                'unit' => 'mAh',
                'is_variant' => false,
                'is_required' => false,
                'sort_order' => 4,
            ]
        );

        VerticalAttribute::firstOrCreate(
            ['vertical_id' => $smartphone->id, 'code' => 'screen_size'],
            [
                'name' => 'Screen Size',
                'type' => AttributeType::Number,
                'unit' => 'inches',
                'is_variant' => false,
                'is_required' => false,
                'sort_order' => 5,
            ]
        );
    }
}
