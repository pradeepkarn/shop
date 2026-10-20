<?php

namespace App\Console\Commands;

use App\Enums\AttributeType;
use App\Enums\BrandStatus;
use App\Enums\ListingStatus;
use App\Enums\ProductQcStatus;
use App\Enums\SellerStatus;
use App\Enums\VariantQcStatus;
use App\Models\Listing;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\VariantKeyNormalizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CatalogAuditCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'catalog:audit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit database catalog and listings for integrity violations and orphaned records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting catalog integrity audit...');
        $this->newLine();

        $checks = [
            'Variants without a product' => $this->checkVariantsWithoutProduct(),
            'Listings on missing variants' => $this->checkListingsOnMissingVariants(),
            'variant_key not matching variant_attributes' => $this->checkVariantKeysMismatch(),
            'Duplicate variant combinations' => $this->checkDuplicateVariantCombinations(),
            'specs not in vertical attributes or active options' => $this->checkSpecsAttributes(),
            'variant_attributes not in vertical attributes or active options' => $this->checkVariantAttributes(),
            'Approved products with non-approved brand' => $this->checkApprovedProductsNonApprovedBrand(),
            'Approved products with no approved variant' => $this->checkApprovedProductsNoApprovedVariant(),
            'Image rows with missing files' => $this->checkImageRowsWithMissingFiles(),
            'Product images whose variant belongs to another product' => $this->checkImagesVariantMismatch(),
            'Selling price exceeding MRP' => $this->checkSellingPriceExceedsMrp(),
            'Active listings by non-approved sellers' => $this->checkActiveListingsNonApprovedSellers(),
        ];

        $tableRows = [];
        $totalViolations = 0;

        foreach ($checks as $name => $result) {
            $count = count($result['violations']);
            $totalViolations += $count;
            $status = $count === 0 ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';

            $tableRows[] = [
                $name,
                $status,
                $count,
                implode(', ', array_slice($result['violations'], 0, 3)).($count > 3 ? ' (+'.($count - 3).' more)' : ''),
            ];
        }

        $this->table(['Integrity Check', 'Status', 'Violations', 'Sample Details'], $tableRows);
        $this->newLine();

        if ($totalViolations > 0) {
            $this->error("Catalog audit completed with {$totalViolations} integrity violation(s).");

            return self::FAILURE;
        }

        $this->info('All catalog integrity checks PASSED with 0 violations.');

        return self::SUCCESS;
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkVariantsWithoutProduct(): array
    {
        $variants = ProductVariant::whereDoesntHave('product')->get();
        $violations = [];

        foreach ($variants as $v) {
            $violations[] = "Variant #{$v->id} (UPID: {$v->upid}) points to missing product_id: {$v->product_id}";
        }

        return ['violations' => $violations];
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkListingsOnMissingVariants(): array
    {
        $listings = Listing::withTrashed()->whereDoesntHave('variant')->get();
        $violations = [];

        foreach ($listings as $l) {
            $violations[] = "Listing #{$l->id} (SKU: {$l->sku}) points to missing variant_id: {$l->product_variant_id}";
        }

        return ['violations' => $violations];
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkVariantKeysMismatch(): array
    {
        $variants = ProductVariant::all();
        $violations = [];

        foreach ($variants as $v) {
            if (empty($v->variant_attributes)) {
                $violations[] = "Variant #{$v->id} has empty variant_attributes";

                continue;
            }

            $expected = VariantKeyNormalizer::normalize($v->variant_attributes);
            if ($v->variant_key !== $expected) {
                $violations[] = "Variant #{$v->id} key '{$v->variant_key}' != expected '{$expected}'";
            }
        }

        return ['violations' => $violations];
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkDuplicateVariantCombinations(): array
    {
        $duplicates = DB::table('product_variants')
            ->select('product_id', 'variant_key', DB::raw('count(*) as aggregate'))
            ->groupBy('product_id', 'variant_key')
            ->having('aggregate', '>', 1)
            ->get();

        $violations = [];
        foreach ($duplicates as $d) {
            $violations[] = "Product #{$d->product_id} has duplicate variant_key '{$d->variant_key}' ({$d->aggregate} times)";
        }

        return ['violations' => $violations];
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkSpecsAttributes(): array
    {
        $products = Product::with('vertical.attributes')->get();
        $violations = [];

        foreach ($products as $p) {
            if (empty($p->specs)) {
                continue;
            }

            if (! $p->vertical) {
                $violations[] = "Product #{$p->id} has specs but missing vertical";

                continue;
            }

            $verticalAttributes = $p->vertical->attributes->keyBy('code');

            foreach ($p->specs as $code => $value) {
                if (! $verticalAttributes->has($code)) {
                    $violations[] = "Product #{$p->id} spec '{$code}' not defined in vertical '{$p->vertical->name}'";

                    continue;
                }

                $attr = $verticalAttributes->get($code);
                if ($attr->is_variant) {
                    $violations[] = "Product #{$p->id} has variant attribute '{$code}' stored in specs instead of variant_attributes";
                }

                if ($attr->type === AttributeType::Select) {
                    $activeValues = collect($attr->options ?? [])->where('is_active', true)->pluck('value')->all();
                    if (is_string($value) && $value !== '' && ! in_array($value, $activeValues, true)) {
                        $violations[] = "Product #{$p->id} spec '{$code}' value '{$value}' is not an active option";
                    }
                } elseif ($attr->type === AttributeType::MultiSelect) {
                    $activeValues = collect($attr->options ?? [])->where('is_active', true)->pluck('value')->all();
                    if (is_array($value)) {
                        foreach ($value as $subVal) {
                            if (! in_array($subVal, $activeValues, true)) {
                                $violations[] = "Product #{$p->id} spec '{$code}' has option '{$subVal}' not in active options";
                            }
                        }
                    }
                }
            }
        }

        return ['violations' => $violations];
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkVariantAttributes(): array
    {
        $variants = ProductVariant::with('product.vertical.attributes')->get();
        $violations = [];

        foreach ($variants as $v) {
            $product = $v->product;
            if (! $product->vertical) {
                continue;
            }

            $verticalVariantAttrs = $product->vertical->attributes->where('is_variant', true)->keyBy('code');

            foreach ($v->variant_attributes as $code => $val) {
                if (! $verticalVariantAttrs->has($code)) {
                    $violations[] = "Variant #{$v->id} has attribute '{$code}' which is not a variant attribute of vertical '{$product->vertical->name}'";

                    continue;
                }

                $attr = $verticalVariantAttrs->get($code);
                $activeValues = collect($attr->options ?? [])->where('is_active', true)->pluck('value')->all();
                if (! in_array($val, $activeValues, true)) {
                    $violations[] = "Variant #{$v->id} attribute '{$code}' value '{$val}' is not in active options";
                }
            }
        }

        return ['violations' => $violations];
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkApprovedProductsNonApprovedBrand(): array
    {
        $products = Product::where('qc_status', ProductQcStatus::Approved)
            ->whereHas('brand', fn ($q) => $q->where('status', '!=', BrandStatus::Approved))
            ->with('brand')
            ->get();

        $violations = [];
        foreach ($products as $p) {
            $brandStatus = $p->brand ? $p->brand->status->value : 'missing';
            $violations[] = "Approved Product #{$p->id} has non-approved brand #{$p->brand_id} (status: {$brandStatus})";
        }

        return ['violations' => $violations];
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkApprovedProductsNoApprovedVariant(): array
    {
        $products = Product::where('qc_status', ProductQcStatus::Approved)
            ->whereDoesntHave('variants', fn ($q) => $q->where('qc_status', VariantQcStatus::Approved))
            ->get();

        $violations = [];
        foreach ($products as $p) {
            $violations[] = "Approved Product #{$p->id} ('{$p->title}') has 0 approved variants";
        }

        return ['violations' => $violations];
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkImageRowsWithMissingFiles(): array
    {
        $images = ProductImage::all();
        $violations = [];

        foreach ($images as $img) {
            if (blank($img->path) || ! Storage::disk('public')->exists($img->path)) {
                $violations[] = "ProductImage #{$img->id} (product #{$img->product_id}) references missing file: '{$img->path}'";
            }
        }

        return ['violations' => $violations];
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkImagesVariantMismatch(): array
    {
        $images = ProductImage::whereNotNull('product_variant_id')->with('variant')->get();
        $violations = [];

        foreach ($images as $img) {
            if ($img->variant && $img->variant->product_id !== $img->product_id) {
                $violations[] = "ProductImage #{$img->id} product_id ({$img->product_id}) != variant product_id ({$img->variant->product_id})";
            }
        }

        return ['violations' => $violations];
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkSellingPriceExceedsMrp(): array
    {
        $listings = Listing::withTrashed()->whereColumn('selling_price', '>', 'mrp')->get();
        $violations = [];

        foreach ($listings as $l) {
            $violations[] = "Listing #{$l->id} (SKU: {$l->sku}) selling_price ({$l->selling_price}) > mrp ({$l->mrp})";
        }

        return ['violations' => $violations];
    }

    /**
     * @return array{violations: array<int, string>}
     */
    protected function checkActiveListingsNonApprovedSellers(): array
    {
        $listings = Listing::where('status', ListingStatus::Active)
            ->whereHas('sellerProfile', fn ($q) => $q->where('status', '!=', SellerStatus::Approved))
            ->with('sellerProfile')
            ->get();

        $violations = [];
        foreach ($listings as $l) {
            $sellerStatus = $l->sellerProfile->status->value;
            $violations[] = "Active Listing #{$l->id} belongs to seller #{$l->seller_profile_id} with status '{$sellerStatus}'";
        }

        return ['violations' => $violations];
    }
}
