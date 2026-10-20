<?php

use App\Enums\AttributeType;
use App\Enums\BrandStatus;
use App\Enums\ListingStatus;
use App\Enums\ProductQcStatus;
use App\Enums\SellerStatus;
use App\Enums\VariantQcStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Vertical;
use App\Models\VerticalAttribute;

test('catalog:audit reports zero issues on valid clean data', function () {
    $user = User::factory()->create();
    $seller = SellerProfile::factory()->create([
        'user_id' => $user->id,
        'status' => SellerStatus::Approved,
    ]);

    $brand = Brand::create(['name' => 'Nike', 'slug' => 'nike', 'status' => BrandStatus::Approved]);
    $cat = Category::create(['name' => 'Fashion', 'slug' => 'fashion']);
    $vert = Vertical::create(['category_id' => $cat->id, 'name' => 'Tee', 'slug' => 'tee', 'hsn_code' => '6109', 'gst_rate' => 5.0]);

    VerticalAttribute::create([
        'vertical_id' => $vert->id,
        'name' => 'Size',
        'code' => 'size',
        'type' => AttributeType::Select,
        'is_variant' => true,
        'options' => [['label' => 'M', 'value' => 'm', 'is_active' => true]],
    ]);

    $product = Product::create([
        'vertical_id' => $vert->id,
        'brand_id' => $brand->id,
        'title' => 'Nike Cotton Tee',
        'description' => 'Running everyday t-shirt',
        'qc_status' => ProductQcStatus::Approved,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'upid' => 'KHPTEST12345678',
        'variant_key' => 'size:m',
        'variant_attributes' => ['size' => 'm'],
        'qc_status' => VariantQcStatus::Approved,
    ]);

    Listing::create([
        'seller_profile_id' => $seller->id,
        'product_variant_id' => $variant->id,
        'sku' => 'NIKE-TEE-M',
        'mrp' => 100000,
        'selling_price' => 80000,
        'stock' => 10,
        'status' => ListingStatus::Active,
    ]);

    $this->artisan('catalog:audit')
        ->assertSuccessful()
        ->expectsOutputToContain('All catalog integrity checks PASSED with 0 violations.');
});

test('catalog:audit detects selling price exceeding MRP and unapproved brand on approved product', function () {
    $user = User::factory()->create();
    $seller = SellerProfile::factory()->create([
        'user_id' => $user->id,
        'status' => SellerStatus::Approved,
    ]);

    $brand = Brand::create(['name' => 'Pending Brand', 'slug' => 'pending-brand', 'status' => BrandStatus::Pending]);
    $cat = Category::create(['name' => 'Sports', 'slug' => 'sports']);
    $vert = Vertical::create(['category_id' => $cat->id, 'name' => 'Gear', 'slug' => 'gear', 'hsn_code' => '9506', 'gst_rate' => 18.0]);

    $product = Product::create([
        'vertical_id' => $vert->id,
        'brand_id' => $brand->id,
        'title' => 'Sample Inconsistent Product',
        'description' => 'This product has an unapproved brand but approved product status',
        'qc_status' => ProductQcStatus::Approved, // Violation: Approved product with pending brand!
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'upid' => 'KHPTEST99999999',
        'variant_key' => 'size:xl',
        'variant_attributes' => ['size' => 'xl'],
        'qc_status' => VariantQcStatus::Approved,
    ]);

    Listing::create([
        'seller_profile_id' => $seller->id,
        'product_variant_id' => $variant->id,
        'sku' => 'INVALID-PRICING-SKU',
        'mrp' => 50000,
        'selling_price' => 70000, // Violation: Selling price > MRP!
        'stock' => 5,
        'status' => ListingStatus::Active,
    ]);

    $this->artisan('catalog:audit')
        ->assertFailed()
        ->expectsOutputToContain('Catalog audit completed with');
});
