<?php

use App\Enums\SellerStatus;
use App\Models\SellerProfile;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] #[Title('Seller Applications')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<SellerProfile>
     */
    #[Computed]
    public function sellers(): LengthAwarePaginator
    {
        return SellerProfile::query()
            ->with(['user', 'reviewer'])
            ->when(filled($this->search), function ($query) {
                $query->where(function ($q) {
                    $q->where('store_name', 'like', '%'.$this->search.'%')
                        ->orWhere('store_slug', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', function ($uq) {
                            $uq->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('email', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->latest('id')
            ->paginate(10);
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Seller Applications</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">Review onboarding applications, manage KYC, and oversee seller statuses.</p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
            <!-- Status Filter -->
            <select wire:model.live="statusFilter"
                    class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                <option value="all">All Statuses</option>
                <option value="pending">Pending Review</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="suspended">Suspended</option>
                <option value="draft">Draft</option>
            </select>

            <!-- Search Input -->
            <div class="relative w-full sm:w-64">
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="Search stores or sellers..."
                       class="w-full rounded-lg border border-zinc-300 bg-white py-2 pl-9 pr-3 text-sm text-zinc-900 placeholder-zinc-400 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:placeholder-zinc-500" />
                <svg class="pointer-events-none absolute left-3 top-2.5 size-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-zinc-600 dark:text-zinc-300">
                <thead class="border-b border-zinc-200 bg-zinc-50 text-xs font-semibold uppercase text-zinc-500 dark:border-zinc-800 dark:bg-zinc-800/50 dark:text-zinc-400">
                    <tr>
                        <th class="px-6 py-3.5">Store / Applicant</th>
                        <th class="px-6 py-3.5">Status</th>
                        <th class="px-6 py-3.5">Notices</th>
                        <th class="px-6 py-3.5">Submitted</th>
                        <th class="px-6 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($this->sellers as $seller)
                        <tr class="transition hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                            <td class="px-6 py-4">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $seller->store_name }}</div>
                                <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $seller->user->name }} ({{ $seller->user->email }})
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                @if ($seller->status === SellerStatus::Approved)
                                    <span class="inline-flex items-center rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950/50 dark:text-green-400 border border-green-200 dark:border-green-800">
                                        Approved
                                    </span>
                                @elseif ($seller->status === SellerStatus::Pending)
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950/50 dark:text-amber-400 border border-amber-200 dark:border-amber-800">
                                        Pending Review
                                    </span>
                                @elseif ($seller->status === SellerStatus::Suspended)
                                    <span class="inline-flex items-center rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/50 dark:text-red-400 border border-red-200 dark:border-red-800">
                                        Suspended
                                    </span>
                                @elseif ($seller->status === SellerStatus::Rejected)
                                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-300 dark:border-zinc-700">
                                        Rejected
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-500 border border-zinc-200">
                                        Draft
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                @if ($seller->bank_updated_at)
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-950/50 dark:text-blue-400 border border-blue-200 dark:border-blue-800" data-test="bank-changed-badge">
                                        Bank details changed
                                    </span>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $seller->submitted_at?->format('M j, Y') ?? 'Not submitted' }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('admin.sellers.show', $seller) }}"
                                   wire:navigate
                                   class="rounded-md border border-zinc-300 px-2.5 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800 transition">
                                    View Application
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">
                                No seller applications found matching the criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->sellers->hasPages())
            <div class="border-t border-zinc-200 p-4 dark:border-zinc-800">
                {{ $this->sellers->links() }}
            </div>
        @endif
    </div>
</div>

