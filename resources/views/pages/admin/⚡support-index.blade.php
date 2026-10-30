<?php

namespace App\Livewire\Admin;

use App\Helpdesk\Enums\RequesterType;
use App\Helpdesk\Enums\TicketCategory;
use App\Helpdesk\Enums\TicketPriority;
use App\Helpdesk\Enums\TicketStatus;
use App\Helpdesk\Models\SupportTicket;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] #[Title('Support Inbox')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    /** "open" (needs reply) by default; "active" is everything not closed. */
    #[Url]
    public string $status = 'open';

    /** all, mine, unassigned */
    #[Url]
    public string $assignee = 'all';

    /** all, customer, seller */
    #[Url]
    public string $requester = 'all';

    #[Url]
    public string $category = 'all';

    #[Url]
    public string $priority = 'all';

    public function mount(): void
    {
        $this->authorize('viewAny', SupportTicket::class);
    }

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'status', 'assignee', 'requester', 'category', 'priority'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $byStatus = SupportTicket::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'open' => (int) ($byStatus[TicketStatus::Open->value] ?? 0),
            'pending' => (int) ($byStatus[TicketStatus::Pending->value] ?? 0),
            'resolved' => (int) ($byStatus[TicketStatus::Resolved->value] ?? 0),
            'unassigned' => SupportTicket::query()->where('status', TicketStatus::Open->value)->whereNull('assigned_admin_id')->count(),
            'mine' => SupportTicket::query()->notClosed()->where('assigned_admin_id', auth('admin')->id())->count(),
        ];
    }

    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        $this->authorize('viewAny', SupportTicket::class);

        return SupportTicket::query()
            ->with('assignee')
            ->when($this->status === 'active', fn ($q) => $q->notClosed())
            ->when(TicketStatus::tryFrom($this->status), fn ($q, $status) => $q->where('status', $status->value))
            ->when($this->assignee === 'mine', fn ($q) => $q->where('assigned_admin_id', auth('admin')->id()))
            ->when($this->assignee === 'unassigned', fn ($q) => $q->whereNull('assigned_admin_id'))
            ->when(RequesterType::tryFrom($this->requester), fn ($q, $type) => $q->where('requester_type', $type->value))
            ->when(TicketCategory::tryFrom($this->category), fn ($q, $category) => $q->where('category', $category->value))
            ->when(TicketPriority::tryFrom($this->priority), fn ($q, $priority) => $q->where('priority', $priority->value))
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($sub) => $sub
                    ->where('ticket_number', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('requester_name', 'like', $term)
                    ->orWhere('requester_email', 'like', $term)
                    ->orWhere('order_number', 'like', $term));
            })
            // Urgent first, then the longest-waiting
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('last_message_at', in_array($this->status, ['open', 'active'], true) ? 'asc' : 'desc')
            ->paginate(20);
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Support Inbox</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Customer and seller requests. Urgent and longest-waiting tickets come first.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @can(\App\Support\AdminPermissions::SUPPORT_SETTINGS)
                <a href="{{ route('admin.support.settings') }}" wire:navigate class="inline-flex items-center rounded-lg border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 shadow-2xs hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">Grievance officer</a>
            @endcan
            <button type="button" wire:click="$set('status', 'open')" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 shadow-2xs hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
                <span class="size-2 rounded-full bg-amber-500"></span>
                <span>Needs reply: {{ $this->counts['open'] }}</span>
            </button>
            <button type="button" wire:click="$set('assignee', 'unassigned')" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 shadow-2xs hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
                <span class="size-2 rounded-full bg-rose-500"></span>
                <span>Unassigned: {{ $this->counts['unassigned'] }}</span>
            </button>
            <button type="button" wire:click="$set('assignee', 'mine')" class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-2.5 py-1 text-xs font-medium text-zinc-700 shadow-2xs hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300">
                <span class="size-2 rounded-full bg-sky-500"></span>
                <span>Mine: {{ $this->counts['mine'] }}</span>
            </button>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-3 rounded-2xl border border-zinc-200 bg-white p-4 shadow-xs sm:grid-cols-2 lg:grid-cols-7 dark:border-zinc-800 dark:bg-zinc-900">
        <div class="relative lg:col-span-2">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Ticket, subject, name, store, email, order…"
                   class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3.5 py-2 pl-9 text-sm text-zinc-900 placeholder-zinc-400 focus:border-zinc-900 focus:bg-white focus:outline-none dark:border-zinc-800 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500 dark:focus:border-white" />
            <svg class="absolute left-3 top-2.5 size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
        </div>

        <select wire:model.live="status" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 focus:border-zinc-900 focus:bg-white focus:outline-none dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200">
            <option value="open">Needs reply ({{ $this->counts['open'] }})</option>
            <option value="pending">Awaiting requester ({{ $this->counts['pending'] }})</option>
            <option value="resolved">Resolved ({{ $this->counts['resolved'] }})</option>
            <option value="closed">Closed</option>
            <option value="active">All active</option>
            <option value="all">All tickets</option>
        </select>

        <select wire:model.live="assignee" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 focus:border-zinc-900 focus:bg-white focus:outline-none dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200">
            <option value="all">Anyone</option>
            <option value="mine">Assigned to me</option>
            <option value="unassigned">Unassigned</option>
        </select>

        <select wire:model.live="requester" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 focus:border-zinc-900 focus:bg-white focus:outline-none dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200">
            <option value="all">Customers &amp; sellers</option>
            <option value="customer">Customers</option>
            <option value="seller">Sellers</option>
        </select>

        <select wire:model.live="category" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 focus:border-zinc-900 focus:bg-white focus:outline-none dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200">
            <option value="all">All topics</option>
            @foreach (TicketCategory::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>

        <select wire:model.live="priority" class="w-full rounded-xl border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 focus:border-zinc-900 focus:bg-white focus:outline-none dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200">
            <option value="all">Any priority</option>
            @foreach (TicketPriority::cases() as $option)
                <option value="{{ $option->value }}">{{ $option->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-400">
                <thead class="border-b border-zinc-200 bg-zinc-50/75 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                    <tr>
                        <th scope="col" class="px-4 py-3.5">Ticket</th>
                        <th scope="col" class="px-4 py-3.5">From</th>
                        <th scope="col" class="px-4 py-3.5">Status</th>
                        <th scope="col" class="px-4 py-3.5">Priority</th>
                        <th scope="col" class="px-4 py-3.5">Assignee</th>
                        <th scope="col" class="px-4 py-3.5">Last activity</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($this->tickets as $ticket)
                        <tr wire:key="ticket-{{ $ticket->id }}" class="transition hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                            <td class="max-w-sm px-4 py-3.5">
                                <a href="{{ route('admin.support.show', ['ticket' => $ticket->ticket_number]) }}" wire:navigate class="group block">
                                    <span class="line-clamp-1 font-medium text-zinc-900 group-hover:underline dark:text-white">{{ $ticket->subject }}</span>
                                    <span class="text-[11px] text-zinc-400">
                                        {{ $ticket->ticket_number }} &middot; {{ $ticket->category->label() }}
                                        @if ($ticket->order_number) &middot; {{ $ticket->order_number }} @endif
                                    </span>
                                </a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5">
                                <div class="flex items-center gap-1.5 font-medium text-zinc-900 dark:text-white">
                                    @if ($ticket->requester_type === RequesterType::Seller)
                                        <span class="rounded bg-violet-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">Seller</span>
                                    @endif
                                    <span>{{ $ticket->requester_name }}</span>
                                </div>
                                <div class="max-w-[180px] truncate text-[11px] text-zinc-400" title="{{ $ticket->requester_email }}">{{ $ticket->requester_email }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $ticket->status->badgeClasses() }}">{{ $ticket->status->label() }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-xs font-semibold {{ $ticket->priority->badgeClasses() }}">{{ $ticket->priority->label() }}</td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-xs">
                                {{ $ticket->assignee?->name ?? '—' }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3.5 text-xs text-zinc-500 dark:text-zinc-400">@datetime($ticket->last_message_at)</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-sm text-zinc-500 dark:text-zinc-400">No tickets match these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div>
        {{ $this->tickets->links() }}
    </div>
</div>
