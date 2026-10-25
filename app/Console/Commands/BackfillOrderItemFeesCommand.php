<?php

namespace App\Console\Commands;

use App\Fees\Contracts\FeeCalculatorInterface;
use App\Fees\Contracts\FeeScheduleServiceInterface;
use App\Fees\DTOs\FeeQuoteInput;
use App\Fees\Events\FeesCalculated;
use App\Fees\Models\OrderItemFeeSnapshot;
use App\Orders\Contracts\OrderItemsFeeLookupInterface;
use App\Orders\DTOs\OrderItemFeeContextDto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillOrderItemFeesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fees:backfill
                            {--limit= : Limit the number of order items to inspect}
                            {--dry-run : Simulate calculations without saving snapshots}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and store fee snapshots for existing order items missing snapshots.';

    public function handle(
        OrderItemsFeeLookupInterface $lookup,
        FeeCalculatorInterface $calculator,
        FeeScheduleServiceInterface $scheduleService,
    ): int {
        $this->info('Starting order item fee backfill...');

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE: No snapshots will be persisted.');
        }

        // Get all already snapshotted order item IDs
        $existingItemIds = OrderItemFeeSnapshot::pluck('order_item_id')->all();
        $this->line('Found '.count($existingItemIds).' items with existing fee snapshots.');

        $items = $lookup->getUnprocessedOrderItemsForBackfill($existingItemIds, $limit);

        if (empty($items)) {
            $this->info('No unprocessed order items found. Everything is up to date.');

            return Command::SUCCESS;
        }

        $this->info('Found '.count($items).' order item(s) to process.');

        /** @var array<string, array<int, OrderItemFeeContextDto>> $grouped */
        $grouped = [];
        foreach ($items as $item) {
            $grouped[$item->sellerOrderId][] = $item;
        }

        $processedCount = 0;
        $skippedCount = 0;

        foreach ($grouped as $sellerOrderId => $sellerItems) {
            $first = $sellerItems[0];
            $schedule = $scheduleService->getScheduleForDate($first->placedAt);

            if (! $schedule) {
                $placedStr = $first->placedAt->format('Y-m-d H:i:s');
                $this->warn("Skipped seller order {$sellerOrderId} (placed at {$placedStr}): No covering fee schedule found.");
                $skippedCount += count($sellerItems);

                continue;
            }

            $quoteInputs = [];
            foreach ($sellerItems as $item) {
                $quoteInputs[] = new FeeQuoteInput(
                    pricePaise: $item->unitPricePaise,
                    productGstRate: $item->gstRate,
                    verticalId: $item->verticalId,
                    weightGrams: $item->weightGrams(),
                    sellerPickupState: $item->sellerPickupState,
                    sellerPickupPincode: $item->sellerPickupPincode,
                    deliveryState: $item->deliveryState,
                    deliveryPincode: $item->deliveryPincode,
                    paymentMethod: $item->paymentMethod,
                    sellerHasPan: $item->sellerHasPan,
                    effectiveDate: $item->placedAt,
                    qty: $item->qty,
                );
            }

            $breakdowns = $calculator->calculateForSellerPackage(
                items: $quoteInputs,
                pickupState: (string) $first->sellerPickupState,
                pickupPincode: $first->sellerPickupPincode,
                deliveryState: $first->deliveryState,
                deliveryPincode: $first->deliveryPincode,
                paymentMethod: $first->paymentMethod,
                sellerHasPan: $first->sellerHasPan,
                effectiveDate: $first->placedAt,
            );

            if (! $dryRun) {
                $sellerDeductions = 0;
                $sellerNetPayout = 0;
                $sellerCredits = 0;

                DB::transaction(function () use ($sellerItems, $breakdowns, $first, $sellerOrderId, &$sellerDeductions, &$sellerNetPayout, &$sellerCredits) {
                    foreach ($sellerItems as $idx => $contextItem) {
                        $breakdown = $breakdowns[$idx];

                        $sellerDeductions += $breakdown->totalDeductions->getPaise();
                        $sellerNetPayout += $breakdown->netPayout->getPaise();
                        $sellerCredits += $breakdown->claimableCredits->getPaise();

                        OrderItemFeeSnapshot::firstOrCreate(
                            ['order_item_id' => $contextItem->orderItemId],
                            [
                                'seller_order_id' => $sellerOrderId,
                                'order_id' => $contextItem->orderId,
                                'fee_schedule_id' => $breakdown->scheduleId,
                                'schedule_version' => $breakdown->scheduleVersion,
                                'selling_price' => $breakdown->sellingPrice->getPaise(),
                                'qty' => $breakdown->qty,
                                'product_gst_rate' => $contextItem->gstRate,
                                'commission_amount' => $breakdown->commission->getPaise(),
                                'closing_fee_amount' => $breakdown->closingFee->getPaise(),
                                'shipping_fee_amount' => $breakdown->shippingFee->getPaise(),
                                'collection_fee_amount' => $breakdown->collectionFee->getPaise(),
                                'gst_on_fees_amount' => $breakdown->gstOnFees->getPaise(),
                                'tcs_amount' => $breakdown->tcs->getPaise(),
                                'tds_amount' => $breakdown->tds->getPaise(),
                                'total_deductions' => $breakdown->totalDeductions->getPaise(),
                                'net_payout' => $breakdown->netPayout->getPaise(),
                                'claimable_credits' => $breakdown->claimableCredits->getPaise(),
                                'snapshot_data' => array_merge($breakdown->toArray(), [
                                    'seller_profile_id' => $first->sellerProfileId,
                                ]),
                            ]
                        );
                    }
                });

                event(new FeesCalculated($first->orderId, [
                    $sellerOrderId => [
                        'total_deductions_paise' => $sellerDeductions,
                        'net_payout_paise' => $sellerNetPayout,
                        'claimable_credits_paise' => $sellerCredits,
                    ],
                ]));
            }

            $processedCount += count($sellerItems);
        }

        $this->info("Backfill complete. Processed: {$processedCount}, Skipped: {$skippedCount}.");

        return Command::SUCCESS;
    }
}
