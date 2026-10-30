<?php

namespace App\Helpdesk\Models;

use App\Helpdesk\Enums\RequesterType;
use App\Helpdesk\Enums\TicketCategory;
use App\Helpdesk\Enums\TicketPriority;
use App\Helpdesk\Enums\TicketStatus;
use App\Models\Admin;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $ticket_number
 * @property RequesterType $requester_type
 * @property int|null $user_id
 * @property string $requester_name
 * @property string $requester_email
 * @property TicketCategory $category
 * @property string $subject
 * @property TicketStatus $status
 * @property TicketPriority $priority
 * @property string|null $order_id
 * @property string|null $order_number
 * @property int|null $assigned_admin_id
 * @property CarbonInterface|null $last_message_at
 * @property CarbonInterface|null $first_response_at
 * @property CarbonInterface|null $resolved_at
 * @property CarbonInterface|null $closed_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read User|null $user
 * @property-read Admin|null $assignee
 * @property-read Collection<int, SupportTicketMessage> $messages
 */
class SupportTicket extends Model
{
    use HasUlids;

    protected $table = 'support_tickets';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requester_type' => RequesterType::class,
            'category' => TicketCategory::class,
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'last_message_at' => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_admin_id');
    }

    /**
     * @return HasMany<SupportTicketMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'support_ticket_id')->orderBy('id');
    }

    /**
     * @param  Builder<SupportTicket>  $query
     */
    public function scopeNotClosed(Builder $query): void
    {
        $query->where('status', '!=', TicketStatus::Closed->value);
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id !== null && (int) $this->user_id === $userId;
    }
}
