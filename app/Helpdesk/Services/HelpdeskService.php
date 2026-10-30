<?php

namespace App\Helpdesk\Services;

use App\Helpdesk\Contracts\CustomerOrderLookupInterface;
use App\Helpdesk\Enums\MessageAuthor;
use App\Helpdesk\Enums\RequesterType;
use App\Helpdesk\Enums\TicketCategory;
use App\Helpdesk\Enums\TicketPriority;
use App\Helpdesk\Enums\TicketStatus;
use App\Helpdesk\Models\SupportTicket;
use App\Helpdesk\Models\SupportTicketMessage;
use App\Mail\SupportTicketMail;
use App\Models\Admin;
use App\Models\User;
use App\Services\AdminActivityLogger;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Ticket lifecycle for requesters (customers now, sellers in phase 2) and admin staff.
 * Staff actions are written to the admin activity log here so every caller is covered.
 */
class HelpdeskService
{
    public function __construct(protected CustomerOrderLookupInterface $orders) {}

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function openCustomerTicket(
        User $user,
        TicketCategory $category,
        string $subject,
        string $body,
        ?string $orderNumber = null,
        array $attachments = [],
    ): SupportTicket {
        $subject = trim($subject);
        $body = trim($body);

        Validator::make(['subject' => $subject, 'body' => $body], [
            'subject' => ['required', 'string', 'min:'.config('helpdesk.subject_min'), 'max:'.config('helpdesk.subject_max')],
            'body' => ['required', 'string', 'min:'.config('helpdesk.body_min'), 'max:'.config('helpdesk.body_max')],
        ], [
            'subject.required' => 'Add a short subject.',
            'body.required' => 'Tell us what happened.',
        ])->validate();
        $this->validateAttachments($attachments);

        $order = null;
        if ($orderNumber !== null && $orderNumber !== '') {
            $order = $this->orders->findForCustomer((int) $user->id, $orderNumber);
            if (! $order) {
                throw new DomainException('We could not find that order on your account.');
            }
        }

        $openCount = SupportTicket::query()->where('user_id', $user->id)->notClosed()->count();
        if ($openCount >= (int) config('helpdesk.max_open_tickets')) {
            throw new DomainException('You already have '.$openCount.' open requests. Please reply on one of those, or close one you no longer need.');
        }

        $todayCount = SupportTicket::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subDay())->count();
        if ($todayCount >= (int) config('helpdesk.max_new_tickets_per_day')) {
            throw new DomainException('You have opened too many requests today. Please try again tomorrow.');
        }

        $ticketId = (string) Str::ulid();
        $stored = $this->storeAttachments($ticketId, $attachments);

        try {
            $ticket = DB::transaction(function () use ($ticketId, $user, $category, $subject, $body, $order, $stored) {
                $ticket = SupportTicket::create([
                    'id' => $ticketId,
                    'ticket_number' => $this->newTicketNumber(),
                    'requester_type' => RequesterType::Customer,
                    'user_id' => $user->id,
                    'requester_name' => $user->name,
                    'requester_email' => $user->email,
                    'category' => $category,
                    'subject' => $subject,
                    'status' => TicketStatus::Open,
                    'priority' => TicketPriority::Normal,
                    'order_id' => $order?->orderId,
                    'order_number' => $order?->orderNumber,
                    'last_message_at' => now(),
                ]);

                $ticket->messages()->create([
                    'author_type' => MessageAuthor::Requester,
                    'user_id' => $user->id,
                    'body' => $body,
                    'attachments' => $stored ?: null,
                ]);

                return $ticket;
            });
        } catch (Throwable $e) {
            $this->deleteStored($stored);

            throw $e;
        }

        $this->notifyRequester($ticket, 'received');

        return $ticket;
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function replyAsRequester(SupportTicket $ticket, User $user, string $body, array $attachments = []): SupportTicketMessage
    {
        if (! $ticket->isOwnedBy((int) $user->id)) {
            throw new DomainException('Request not found.');
        }

        $body = $this->validateReply($body);
        $this->validateAttachments($attachments);
        $stored = $this->storeAttachments($ticket->id, $attachments);

        try {
            return DB::transaction(function () use ($ticket, $user, $body, $stored) {
                $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
                if (! $locked->status->acceptsReplies()) {
                    throw new DomainException('This request is closed. Please open a new one.');
                }

                $message = $locked->messages()->create([
                    'author_type' => MessageAuthor::Requester,
                    'user_id' => $user->id,
                    'body' => $body,
                    'attachments' => $stored ?: null,
                ]);

                // Any requester reply puts the ticket back with staff
                $locked->update([
                    'status' => TicketStatus::Open,
                    'resolved_at' => null,
                    'last_message_at' => now(),
                ]);

                return $message;
            });
        } catch (Throwable $e) {
            $this->deleteStored($stored);

            throw $e;
        }
    }

    public function closeByRequester(SupportTicket $ticket, User $user): SupportTicket
    {
        if (! $ticket->isOwnedBy((int) $user->id)) {
            throw new DomainException('Request not found.');
        }

        return DB::transaction(function () use ($ticket) {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            if ($locked->status === TicketStatus::Closed) {
                return $locked;
            }

            $this->applyStatus($locked, TicketStatus::Closed);
            $this->systemNote($locked, 'Closed by the customer.', internal: false);

            return $locked;
        });
    }

    /**
     * A public reply (default: waiting on the customer afterwards) or an internal note (no status change, no email).
     *
     * @param  array<int, UploadedFile>  $attachments
     */
    public function replyAsStaff(
        SupportTicket $ticket,
        Admin $admin,
        string $body,
        array $attachments = [],
        bool $internal = false,
        ?TicketStatus $statusAfter = null,
    ): SupportTicketMessage {
        $body = $this->validateReply($body);
        $this->validateAttachments($attachments);
        $stored = $this->storeAttachments($ticket->id, $attachments);

        try {
            $message = DB::transaction(function () use ($ticket, $admin, $body, $stored, $internal, $statusAfter) {
                $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
                if (! $internal && ! $locked->status->acceptsReplies()) {
                    throw new DomainException('This ticket is closed. Reopen it before replying to the customer.');
                }

                $message = $locked->messages()->create([
                    'author_type' => MessageAuthor::Staff,
                    'admin_id' => $admin->id,
                    'body' => $body,
                    'is_internal' => $internal,
                    'attachments' => $stored ?: null,
                ]);

                if (! $internal) {
                    $changes = ['last_message_at' => now()];
                    if ($locked->first_response_at === null) {
                        $changes['first_response_at'] = now();
                    }
                    if ($locked->assigned_admin_id === null) {
                        $changes['assigned_admin_id'] = $admin->id;
                    }
                    $locked->fill($changes);
                    $this->applyStatus($locked, $statusAfter ?? TicketStatus::Pending);
                }

                AdminActivityLogger::log($internal ? 'support.note_added' : 'support.replied', $locked, [
                    'ticket_number' => $locked->ticket_number,
                    'message_id' => $message->id,
                    'status' => $locked->status->value,
                ], $admin);

                return $message;
            });
        } catch (Throwable $e) {
            $this->deleteStored($stored);

            throw $e;
        }

        if (! $internal) {
            $this->notifyRequester($ticket->refresh(), 'staff_reply');
        }

        return $message;
    }

    public function setStatus(SupportTicket $ticket, Admin $admin, TicketStatus $status): SupportTicket
    {
        $previous = $ticket->status;
        if ($previous === $status) {
            return $ticket;
        }

        DB::transaction(function () use ($ticket, $admin, $status, $previous) {
            $this->applyStatus($ticket, $status);

            $note = match ($status) {
                TicketStatus::Resolved => 'Marked as resolved by support.',
                TicketStatus::Closed => 'Closed by support.',
                TicketStatus::Open => $previous === TicketStatus::Closed ? 'Reopened by support.' : null,
                TicketStatus::Pending => null,
            };
            if ($note) {
                $this->systemNote($ticket, $note, internal: false);
            }

            AdminActivityLogger::log('support.status_changed', $ticket, [
                'ticket_number' => $ticket->ticket_number,
                'from' => $previous->value,
                'to' => $status->value,
            ], $admin);
        });

        if ($status === TicketStatus::Resolved) {
            $this->notifyRequester($ticket, 'resolved');
        }

        return $ticket;
    }

    public function setPriority(SupportTicket $ticket, Admin $admin, TicketPriority $priority): SupportTicket
    {
        $previous = $ticket->priority;
        if ($previous === $priority) {
            return $ticket;
        }

        $ticket->update(['priority' => $priority]);

        AdminActivityLogger::log('support.priority_changed', $ticket, [
            'ticket_number' => $ticket->ticket_number,
            'from' => $previous->value,
            'to' => $priority->value,
        ], $admin);

        return $ticket;
    }

    /**
     * Assign to an active staff member, or unassign with null.
     */
    public function assign(SupportTicket $ticket, Admin $actor, ?int $assigneeId): SupportTicket
    {
        $assignee = null;
        if ($assigneeId !== null) {
            $assignee = Admin::query()->where('is_active', true)->find($assigneeId);
            if (! $assignee) {
                throw new DomainException('That staff member is not available.');
            }
        }

        if ((int) $ticket->assigned_admin_id === (int) $assignee?->id) {
            return $ticket;
        }

        DB::transaction(function () use ($ticket, $actor, $assignee) {
            $previousId = $ticket->assigned_admin_id;
            $ticket->update(['assigned_admin_id' => $assignee?->id]);

            $this->systemNote($ticket, $assignee
                ? "Assigned to {$assignee->name} by {$actor->name}."
                : "Unassigned by {$actor->name}.", internal: true);

            AdminActivityLogger::log('support.assigned', $ticket, [
                'ticket_number' => $ticket->ticket_number,
                'from_admin_id' => $previousId,
                'to_admin_id' => $assignee?->id,
            ], $actor);
        });

        return $ticket->load('assignee');
    }

    /**
     * Close resolved tickets with no activity for the configured number of days.
     */
    public function closeStaleResolved(?int $days = null): int
    {
        $days ??= (int) config('helpdesk.auto_close_resolved_days');
        $closed = 0;

        SupportTicket::query()
            ->where('status', TicketStatus::Resolved->value)
            ->where('last_message_at', '<=', now()->subDays($days))
            ->where(fn ($q) => $q->whereNull('resolved_at')->orWhere('resolved_at', '<=', now()->subDays($days)))
            ->orderBy('id')
            ->each(function (SupportTicket $ticket) use ($days, &$closed) {
                DB::transaction(function () use ($ticket, $days) {
                    $this->applyStatus($ticket, TicketStatus::Closed);
                    $this->systemNote($ticket, "Closed automatically after {$days} days without a reply.", internal: false);
                });
                $closed++;
            });

        return $closed;
    }

    /**
     * Sets the status with its timestamps and saves the ticket.
     */
    protected function applyStatus(SupportTicket $ticket, TicketStatus $status): void
    {
        $ticket->status = $status;
        $ticket->resolved_at = $status === TicketStatus::Resolved ? now() : ($status === TicketStatus::Closed ? $ticket->resolved_at : null);
        $ticket->closed_at = $status === TicketStatus::Closed ? now() : null;
        $ticket->save();
    }

    protected function systemNote(SupportTicket $ticket, string $body, bool $internal): void
    {
        $ticket->messages()->create([
            'author_type' => MessageAuthor::System,
            'body' => $body,
            'is_internal' => $internal,
        ]);
    }

    protected function validateReply(string $body): string
    {
        $body = trim($body);

        Validator::make(['body' => $body], [
            'body' => ['required', 'string', 'max:'.config('helpdesk.body_max')],
        ], [
            'body.required' => 'Write a message first.',
        ])->validate();

        return $body;
    }

    /**
     * @param  array<int, mixed>  $attachments
     */
    protected function validateAttachments(array $attachments): void
    {
        Validator::make(['attachments' => $attachments], [
            'attachments' => ['array', 'max:'.config('helpdesk.max_attachments')],
            'attachments.*' => ['file', 'mimes:'.implode(',', config('helpdesk.attachment_mimes')), 'max:'.config('helpdesk.attachment_max_kb')],
        ], [
            'attachments.max' => 'You can attach up to '.config('helpdesk.max_attachments').' files.',
            'attachments.*.mimes' => 'Attach images (JPG, PNG, WEBP) or PDF files only.',
            'attachments.*.max' => 'Each file must be '.((int) config('helpdesk.attachment_max_kb') / 1024).' MB or smaller.',
        ])->validate();
    }

    /**
     * @param  array<int, UploadedFile>  $attachments
     * @return list<array{path: string, name: string, mime: string, size: int}>
     */
    protected function storeAttachments(string $ticketId, array $attachments): array
    {
        $stored = [];
        foreach ($attachments as $file) {
            $path = $file->store("support_attachments/{$ticketId}", 'local');
            if ($path === false) {
                $this->deleteStored($stored);

                throw new RuntimeException('Could not save the attachment. Please try again.');
            }

            $stored[] = [
                'path' => $path,
                'name' => Str::limit(basename($file->getClientOriginalName()), 120, ''),
                'mime' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ];
        }

        return $stored;
    }

    /**
     * @param  list<array{path: string, name: string, mime: string, size: int}>  $stored
     */
    protected function deleteStored(array $stored): void
    {
        if ($stored) {
            Storage::disk('local')->delete(array_column($stored, 'path'));
        }
    }

    protected function newTicketNumber(): string
    {
        do {
            $number = 'SUP-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (SupportTicket::query()->where('ticket_number', $number)->exists());

        return $number;
    }

    protected function notifyRequester(SupportTicket $ticket, string $event): void
    {
        if ($ticket->requester_email === '') {
            return;
        }

        DB::afterCommit(fn () => Mail::to($ticket->requester_email)->queue(new SupportTicketMail($ticket, $event)));
    }
}
