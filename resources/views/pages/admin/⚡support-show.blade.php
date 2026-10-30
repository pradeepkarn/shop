<?php

namespace App\Livewire\Admin;

use App\Helpdesk\Enums\RequesterType;
use App\Helpdesk\Enums\TicketPriority;
use App\Helpdesk\Enums\TicketStatus;
use App\Helpdesk\Models\SupportTicket;
use App\Helpdesk\Models\SupportTicketMessage;
use App\Helpdesk\Services\HelpdeskService;
use App\Models\Admin;
use App\Support\AdminPermissions;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.admin')] #[Title('Support Ticket')] class extends Component {
    use WithFileUploads;

    public string $ticketNumber;

    public string $reply = '';

    public array $attachments = [];

    public bool $internal = false;

    /** Status after a public reply. */
    public string $statusAfter = 'pending';

    public function mount(string $ticket): void
    {
        $this->authorize('viewAny', SupportTicket::class);

        $this->ticketNumber = $ticket;
        abort_unless($this->ticket, 404);
    }

    #[Computed]
    public function ticket(): ?SupportTicket
    {
        return SupportTicket::query()->with(['assignee', 'user', 'sellerProfile'])->where('ticket_number', $this->ticketNumber)->first();
    }

    /**
     * @return Collection<int, SupportTicketMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->ticket->messages()->with('admin')->get();
    }

    /**
     * @return Collection<int, Admin>
     */
    #[Computed]
    public function staff(): Collection
    {
        return Admin::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    /**
     * The requester's other tickets, newest first.
     *
     * @return Collection<int, SupportTicket>
     */
    #[Computed]
    public function otherTickets(): Collection
    {
        if (! $this->ticket->user_id) {
            return collect();
        }

        return SupportTicket::query()
            ->where('user_id', $this->ticket->user_id)
            ->whereKeyNot($this->ticket->id)
            ->latest('last_message_at')
            ->limit(5)
            ->get();
    }

    protected function admin(): Admin
    {
        /** @var Admin $admin */
        $admin = auth('admin')->user();

        return $admin;
    }

    protected function refreshTicket(): void
    {
        unset($this->ticket, $this->messages, $this->otherTickets);
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function sendReply(HelpdeskService $helpdesk): void
    {
        $this->authorize('reply', SupportTicket::class);
        $this->resetErrorBag();

        $statusAfter = TicketStatus::tryFrom($this->statusAfter) ?? TicketStatus::Pending;

        try {
            $helpdesk->replyAsStaff($this->ticket, $this->admin(), $this->reply, $this->attachments, $this->internal, $statusAfter);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(str_starts_with($field, 'attachments') ? 'attachments' : 'reply', $messages[0]);
            }

            return;
        } catch (\DomainException $e) {
            $this->addError('reply', $e->getMessage());

            return;
        }

        session()->flash('success', $this->internal ? 'Internal note added.' : 'Reply sent to the '.$this->ticket->requester_type->value.'.');
        $this->reset(['reply', 'attachments', 'internal']);
        $this->statusAfter = 'pending';
        $this->refreshTicket();
    }

    public function updateStatus(string $status, HelpdeskService $helpdesk): void
    {
        $this->authorize('reply', SupportTicket::class);

        $helpdesk->setStatus($this->ticket, $this->admin(), TicketStatus::from($status));
        session()->flash('success', 'Status updated.');
        $this->refreshTicket();
    }

    public function updatePriority(string $priority, HelpdeskService $helpdesk): void
    {
        $this->authorize('reply', SupportTicket::class);

        $helpdesk->setPriority($this->ticket, $this->admin(), TicketPriority::from($priority));
        session()->flash('success', 'Priority updated.');
        $this->refreshTicket();
    }

    public function assignTo(string $adminId, HelpdeskService $helpdesk): void
    {
        $this->authorize('assign', SupportTicket::class);

        try {
            $helpdesk->assign($this->ticket, $this->admin(), $adminId === '' ? null : (int) $adminId);
            session()->flash('success', 'Assignment updated.');
        } catch (\DomainException $e) {
            session()->flash('error', $e->getMessage());
        }
        $this->refreshTicket();
    }

    /**
     * Staff who can reply may take an unassigned ticket without the assign permission.
     */
    public function takeTicket(HelpdeskService $helpdesk): void
    {
        $this->authorize('reply', SupportTicket::class);

        if ($this->ticket->assigned_admin_id !== null) {
            session()->flash('error', 'Someone else has already taken this ticket.');
            $this->refreshTicket();

            return;
        }

        $helpdesk->assign($this->ticket, $this->admin(), $this->admin()->id);
        session()->flash('success', 'Ticket assigned to you.');
        $this->refreshTicket();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <a href="{{ route('admin.support.index') }}" wire:navigate class="text-xs font-medium text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-white">&larr; Support inbox</a>
            <h1 class="mt-1 break-words text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->ticket->subject }}</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ $this->ticket->ticket_number }} &middot; {{ $this->ticket->category->label() }} &middot; opened @datetime($this->ticket->created_at)
            </p>
        </div>
        <span class="inline-flex shrink-0 items-center self-start rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 {{ $this->ticket->status->badgeClasses() }}">{{ $this->ticket->status->label() }}</span>
    </div>

    @if (session()->has('success'))
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300">{{ session('error') }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Thread & reply -->
        <div class="space-y-6 lg:col-span-2">
            <x-support.thread :ticket="$this->ticket" :messages="$this->messages" viewer="staff" />

            @can('reply', \App\Helpdesk\Models\SupportTicket::class)
                <form wire:submit="sendReply" @class([
                    'space-y-3 rounded-2xl border p-5 shadow-xs',
                    'border-amber-300 bg-amber-50/60 dark:border-amber-700 dark:bg-amber-950/20' => $internal,
                    'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900' => ! $internal,
                ])>
                    <div class="flex items-center gap-1 rounded-lg bg-zinc-100 p-1 text-xs font-semibold dark:bg-zinc-800 w-fit">
                        <button type="button" wire:click="$set('internal', false)" @class(['rounded-md px-3 py-1.5 transition', 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-700 dark:text-white' => ! $internal, 'text-zinc-500 dark:text-zinc-400' => $internal])>Reply to {{ $this->ticket->requester_type->value }}</button>
                        <button type="button" wire:click="$set('internal', true)" @class(['rounded-md px-3 py-1.5 transition', 'bg-white text-amber-800 shadow-xs dark:bg-zinc-700 dark:text-amber-300' => $internal, 'text-zinc-500 dark:text-zinc-400' => ! $internal])>Internal note</button>
                    </div>

                    <textarea wire:model="reply" rows="5" maxlength="{{ config('helpdesk.body_max') }}"
                              placeholder="{{ $internal ? 'Only staff can see this note.' : 'Write your reply to the '.$this->ticket->requester_type->value.'.' }}"
                              class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3.5 py-2.5 text-sm text-zinc-900 placeholder-zinc-400 focus:border-zinc-900 focus:bg-white focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-white"></textarea>
                    @error('reply') <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror

                    <x-support.attachments-input :files="$attachments" />

                    <div class="flex flex-col gap-3 border-t border-zinc-100 pt-3 sm:flex-row sm:items-center sm:justify-end dark:border-zinc-800">
                        @unless ($internal)
                            <label class="flex items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                                <span>Then set status to</span>
                                <select wire:model="statusAfter" class="rounded-lg border border-zinc-200 bg-zinc-50 px-2 py-1.5 text-xs text-zinc-800 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                                    <option value="pending">Awaiting {{ $this->ticket->requester_type->value }}</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="open">Open</option>
                                </select>
                            </label>
                        @endunless
                        <button type="submit" wire:loading.attr="disabled" wire:target="sendReply,attachments"
                                class="inline-flex items-center justify-center rounded-xl bg-zinc-900 px-5 py-2 text-sm font-semibold text-white shadow-xs hover:bg-zinc-800 disabled:opacity-60 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                            {{ $internal ? 'Add note' : 'Send reply' }}
                        </button>
                    </div>
                </form>
            @endcan
        </div>

        <!-- Side panel -->
        <aside class="space-y-4">
            <div class="space-y-4 rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400">Ticket</h2>

                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Status</label>
                    @can('reply', \App\Helpdesk\Models\SupportTicket::class)
                        <select wire:change="updateStatus($event.target.value)" class="w-full rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                            @foreach (TicketStatus::cases() as $option)
                                <option value="{{ $option->value }}" @selected($this->ticket->status === $option)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    @else
                        <p class="text-sm text-zinc-900 dark:text-white">{{ $this->ticket->status->label() }}</p>
                    @endcan
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Priority</label>
                    @can('reply', \App\Helpdesk\Models\SupportTicket::class)
                        <select wire:change="updatePriority($event.target.value)" class="w-full rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                            @foreach (TicketPriority::cases() as $option)
                                <option value="{{ $option->value }}" @selected($this->ticket->priority === $option)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    @else
                        <p class="text-sm {{ $this->ticket->priority->badgeClasses() }}">{{ $this->ticket->priority->label() }}</p>
                    @endcan
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-zinc-500 dark:text-zinc-400">Assignee</label>
                    @can('assign', \App\Helpdesk\Models\SupportTicket::class)
                        <select wire:change="assignTo($event.target.value)" class="w-full rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200">
                            <option value="">Unassigned</option>
                            @foreach ($this->staff as $member)
                                <option value="{{ $member->id }}" @selected((int) $this->ticket->assigned_admin_id === $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    @else
                        <p class="text-sm text-zinc-900 dark:text-white">{{ $this->ticket->assignee?->name ?? 'Unassigned' }}</p>
                        @if (! $this->ticket->assigned_admin_id)
                            @can('reply', \App\Helpdesk\Models\SupportTicket::class)
                                <button type="button" wire:click="takeTicket" class="mt-1 text-xs font-semibold text-sky-700 hover:text-sky-800 dark:text-sky-400">Take this ticket</button>
                            @endcan
                        @endif
                    @endcan
                </div>

                <dl class="space-y-2 border-t border-zinc-100 pt-4 text-xs dark:border-zinc-800">
                    <div class="flex justify-between gap-2"><dt class="text-zinc-500 dark:text-zinc-400">First response</dt><dd class="text-right text-zinc-900 dark:text-white">{{ $this->ticket->first_response_at ? format_datetime($this->ticket->first_response_at) : '—' }}</dd></div>
                    <div class="flex justify-between gap-2"><dt class="text-zinc-500 dark:text-zinc-400">Last activity</dt><dd class="text-right text-zinc-900 dark:text-white">@datetime($this->ticket->last_message_at)</dd></div>
                    @if ($this->ticket->resolved_at)
                        <div class="flex justify-between gap-2"><dt class="text-zinc-500 dark:text-zinc-400">Resolved</dt><dd class="text-right text-zinc-900 dark:text-white">@datetime($this->ticket->resolved_at)</dd></div>
                    @endif
                    @if ($this->ticket->closed_at)
                        <div class="flex justify-between gap-2"><dt class="text-zinc-500 dark:text-zinc-400">Closed</dt><dd class="text-right text-zinc-900 dark:text-white">@datetime($this->ticket->closed_at)</dd></div>
                    @endif
                </dl>
            </div>

            <div class="space-y-3 rounded-2xl border border-zinc-200 bg-white p-5 text-sm shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
                <h2 class="text-xs font-bold uppercase tracking-wider text-zinc-400">{{ $this->ticket->requester_type->label() }}</h2>
                <div>
                    <p class="font-medium text-zinc-900 dark:text-white">{{ $this->ticket->requester_name }}</p>
                    <p class="break-all text-xs text-zinc-500 dark:text-zinc-400">{{ $this->ticket->requester_email }}</p>
                    @if ($this->ticket->requester_type === RequesterType::Seller)
                        @if ($this->ticket->sellerProfile)
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Store status: {{ $this->ticket->sellerProfile->status->label() }}</p>
                            @can(AdminPermissions::SELLERS_VIEW)
                                <a href="{{ route('admin.sellers.show', ['sellerProfile' => $this->ticket->seller_profile_id]) }}" wire:navigate class="mt-1 inline-block text-xs font-semibold text-sky-700 hover:text-sky-800 dark:text-sky-400">View seller &rarr;</a>
                            @endcan
                        @else
                            <p class="mt-1 text-xs italic text-zinc-400">Seller profile deleted</p>
                        @endif
                    @elseif ($this->ticket->user_id)
                        @can(AdminPermissions::CUSTOMERS_VIEW)
                            <a href="{{ route('admin.customers.show', ['user' => $this->ticket->user_id]) }}" wire:navigate class="mt-1 inline-block text-xs font-semibold text-sky-700 hover:text-sky-800 dark:text-sky-400">View customer &rarr;</a>
                        @endcan
                    @else
                        <p class="mt-1 text-xs italic text-zinc-400">Account deleted</p>
                    @endif
                </div>

                @if ($this->ticket->order_number)
                    <div class="border-t border-zinc-100 pt-3 dark:border-zinc-800">
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">Order</p>
                        @can(AdminPermissions::ORDERS_VIEW)
                            <a href="{{ route('admin.orders.show', ['order' => $this->ticket->order_id]) }}" wire:navigate class="font-medium text-sky-700 hover:text-sky-800 dark:text-sky-400">{{ $this->ticket->order_number }} &rarr;</a>
                        @else
                            <p class="font-medium text-zinc-900 dark:text-white">{{ $this->ticket->order_number }}</p>
                        @endcan
                    </div>
                @endif

                @if ($this->otherTickets->isNotEmpty())
                    <div class="border-t border-zinc-100 pt-3 dark:border-zinc-800">
                        <p class="mb-1.5 text-xs text-zinc-500 dark:text-zinc-400">Other requests</p>
                        <ul class="space-y-1.5">
                            @foreach ($this->otherTickets as $other)
                                <li>
                                    <a href="{{ route('admin.support.show', ['ticket' => $other->ticket_number]) }}" wire:navigate class="block text-xs hover:underline">
                                        <span class="line-clamp-1 font-medium text-zinc-800 dark:text-zinc-200">{{ $other->subject }}</span>
                                        <span class="text-zinc-400">{{ $other->ticket_number }} &middot; {{ $other->status->label() }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </aside>
    </div>
</div>
