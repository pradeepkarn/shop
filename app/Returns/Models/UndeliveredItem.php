<?php

namespace App\Returns\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * An item the courier did not deliver on a partial delivery. Phase B decides its outcome.
 *
 * @property string $id
 * @property string $seller_order_id
 * @property string $shipment_id
 * @property int $order_item_id
 * @property string $status
 * @property int|null $recorded_by_admin_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class UndeliveredItem extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    protected $table = 'undelivered_items';

    protected $fillable = [
        'id',
        'seller_order_id',
        'shipment_id',
        'order_item_id',
        'status',
        'recorded_by_admin_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_item_id' => 'integer',
            'recorded_by_admin_id' => 'integer',
        ];
    }
}
