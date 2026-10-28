<?php

namespace App\Returns\Models;

use App\Returns\Enums\ReturnReason;
use App\Returns\Enums\ReturnRequestStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer's request to return units of one seller package.
 *
 * @property string $id
 * @property string $order_id
 * @property string $seller_order_id
 * @property int $user_id
 * @property ReturnReason $reason
 * @property string $preference
 * @property ReturnRequestStatus $status
 * @property string|null $customer_notes
 * @property array<int, string>|null $photos
 * @property string|null $info_request_message
 * @property string|null $customer_response
 * @property string|null $rejection_reason
 * @property string|null $closed_reason
 * @property string|null $reverse_shipment_id
 * @property Carbon|null $return_deadline_at
 * @property int|null $window_override_by_admin_id
 * @property int|null $decided_by_admin_id
 * @property Carbon|null $decided_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ReturnRequestItem> $items
 */
class ReturnRequest extends Model
{
    use HasUlids;

    public const PREFERENCE_REFUND = 'refund';

    public const PREFERENCE_REPLACEMENT = 'replacement';

    protected $table = 'return_requests';

    protected $fillable = [
        'order_id',
        'seller_order_id',
        'user_id',
        'reason',
        'preference',
        'status',
        'customer_notes',
        'photos',
        'info_request_message',
        'customer_response',
        'rejection_reason',
        'closed_reason',
        'reverse_shipment_id',
        'return_deadline_at',
        'window_override_by_admin_id',
        'decided_by_admin_id',
        'decided_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'reason' => ReturnReason::class,
            'status' => ReturnRequestStatus::class,
            'photos' => 'array',
            'return_deadline_at' => 'datetime',
            'window_override_by_admin_id' => 'integer',
            'decided_by_admin_id' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ReturnRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class, 'return_request_id');
    }

    /**
     * @return array<int, array{units: int, line_qty: int}>
     */
    public function lines(): array
    {
        return $this->items->mapWithKeys(fn (ReturnRequestItem $item) => [
            $item->order_item_id => ['units' => $item->units, 'line_qty' => $item->line_qty],
        ])->all();
    }
}
