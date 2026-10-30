<?php

namespace App\Livewire\Seller;

use App\Helpdesk\Enums\RequesterType;
use App\Helpdesk\Enums\TicketStatus;
use App\Helpdesk\Models\SupportTicket;
use App\Helpdesk\Models\SupportTicketMessage;
use App\Helpdesk\Services\HelpdeskService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.seller')] #[Title('Support Request')] class extends Component {
    use WithFileUploads;

    public string $ticketNumber;

    public string $reply = '';

    public array $attachments = [];

    public function mount(string $ticket): void
    {
        $this->ticketNumber = $ticket;

        // 404 rather than 403 so ticket numbers cannot be probed
        abort_unless($this->ticket && auth()->user()->can('view', $this->ticket), 404);
    }

    #[Computed]
    public function ticket(): ?SupportTicket
    {
        // Customer tickets live in the storefront account
        return SupportTicket::query()
            ->where('ticket_number', $this->ticketNumber)
            ->where('requester_type', RequesterType::Seller->value)
            ->first();
    }

    /**
     * @return Collection<int, SupportTicketMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->ticket->messages()->where('is_internal', false)->get();
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function sendReply(HelpdeskService $helpdesk): void
    {
        $this->authorize('view', $this->ticket);
        $this->resetErrorBag();

        try {
            $helpdesk->replyAsRequester($this->ticket, auth()->user(), $this->reply, $this->attachments);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(str_starts_with($field, 'attachments') ? 'attachments' : 'reply', $messages[0]);
            }

            return;
        } catch (\DomainException $e) {
            $this->addError('reply', $e->getMessage());

            return;
        }

        $this->reset(['reply', 'attachments']);
        unset($this->ticket, $this->messages);
    }

    public function closeTicket(HelpdeskService $helpdesk): void
    {
        $this->authorize('view', $this->ticket);

        $helpdesk->closeByRequester($this->ticket, auth()->user());
        unset($this->ticket, $this->messages);
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <a href="{{ route('seller.support.index') }}" wire:navigate class="text-xs font-medium text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white">&larr; Seller Support</a>

    @if (session()->has('support_success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ session('support_success') }}
        </div>
    @endif

    <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs sm:p-6 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold text-zinc-400">{{ $this->ticket->ticket_number }} &middot; {{ $this->ticket->category->label() }}</p>
                <h1 class="mt-1 break-words text-xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->ticket->subject }}</h1>
                @if ($this->ticket->seller_order_id)
                    <a href="{{ route('seller.orders.show', ['sellerOrder' => $this->ticket->seller_order_id]) }}" wire:navigate class="mt-1 inline-block text-xs font-semibold text-amber-600 hover:text-amber-700 dark:text-amber-400">
                        Order {{ $this->ticket->order_number }} &rarr;
                    </a>
                @endif
            </div>
            <span class="inline-flex shrink-0 items-center self-start rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $this->ticket->status->badgeClasses() }}">
                {{ $this->ticket->status->requesterLabel() }}
            </span>
        </div>
        @if ($this->ticket->reply_due_at && $this->ticket->status === TicketStatus::Pending)
            <p data-test="seller-reply-due" @class([
                'mt-3 rounded-lg px-3 py-2 text-xs font-semibold',
                'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300' => $this->ticket->reply_due_at->isPast(),
                'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' => ! $this->ticket->reply_due_at->isPast(),
            ])>
                {{ $this->ticket->reply_due_at->isPast() ? 'Your reply is overdue. It was due' : 'Please reply by' }} @datetime($this->ticket->reply_due_at)
            </p>
        @endif
    </div>

    <x-support.thread :ticket="$this->ticket" :messages="$this->messages" viewer="requester" />

    @if ($this->ticket->status->acceptsReplies())
        <form wire:submit="sendReply" class="space-y-3 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <label for="reply" class="block text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                {{ $this->ticket->status === TicketStatus::Resolved ? 'Still need help? Reply to reopen this request.' : 'Reply' }}
            </label>
            <textarea id="reply" wire:model="reply" rows="4" maxlength="{{ config('helpdesk.body_max') }}"
                      class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3.5 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 focus:border-zinc-900 focus:bg-white focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-white"
                      placeholder="Write your message"></textarea>
            @error('reply') <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

            <x-support.attachments-input :files="$attachments" />

            <div class="flex flex-col-reverse gap-3 border-t border-zinc-100 pt-3 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                <button type="button" wire:click="closeTicket" wire:confirm="Close this request? You will not be able to reply on it again."
                        class="text-xs font-semibold text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white">
                    My issue is sorted, close this request
                </button>
                <button type="submit" wire:loading.attr="disabled" wire:target="sendReply,attachments"
                        class="inline-flex items-center justify-center rounded-xl bg-amber-500 px-6 py-2.5 text-xs font-bold uppercase tracking-wider text-white shadow-xs hover:bg-amber-600 disabled:opacity-60 transition">
                    Send reply
                </button>
            </div>
        </form>
    @else
        <div class="rounded-2xl border border-zinc-200 bg-zinc-50 p-5 text-center text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
            This request is closed.
            <a href="{{ route('seller.support.create', array_filter(['order' => $this->ticket->seller_order_id])) }}" wire:navigate class="font-semibold text-amber-600 hover:text-amber-700 dark:text-amber-400">Open a new request</a>
            if you still need help.
        </div>
    @endif
</div>
