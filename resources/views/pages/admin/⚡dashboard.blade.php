<?php

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Admin Dashboard')] class extends Component {
    #[Computed]
    public function totalCustomers(): int
    {
        return User::count();
    }

    #[Computed]
    public function newCustomersThisWeek(): int
    {
        return User::where('created_at', '>=', now()->startOfWeek())->count();
    }
}; ?>

<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Dashboard</h2>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Overview of customers and platform activity.</p>
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-2">
        <!-- Total Customers Card -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Total Customers</p>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white" data-test="total-customers-count">
                        {{ number_format($this->totalCustomers) }}
                    </p>
                </div>
                <div class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800/60">
                <a href="{{ route('admin.customers') }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">
                    <span>View all customers</span>
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>

        <!-- New Customers This Week Card -->
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">New Customers This Week</p>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white" data-test="new-customers-count">
                        {{ number_format($this->newCustomersThisWeek) }}
                    </p>
                </div>
                <div class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                </div>
            </div>
            <div class="mt-4 border-t border-zinc-100 pt-3 dark:border-zinc-800/60">
                <span class="text-xs text-zinc-500 dark:text-zinc-400">Registered since {{ now()->startOfWeek()->format('M j, Y') }}</span>
            </div>
        </div>
    </div>
</div>

