<?php

use App\Models\User;
use App\Services\AdminActivityLogger;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Customer Details')] class extends Component {
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    public function toggleActive(): void
    {
        $this->authorize('customers.manage');

        $this->user->is_active = ! $this->user->is_active;
        $this->user->save();

        AdminActivityLogger::log($this->user->is_active ? 'customer.activated' : 'customer.deactivated', $this->user, [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'is_active' => $this->user->is_active,
        ]);

        session()->flash('status', 'Customer status updated to '.($this->user->is_active ? 'active' : 'inactive').'.');
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.customers') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-medium text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white transition">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Customers</span>
        </a>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 border-b border-zinc-200 p-6 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
            <div class="flex items-center gap-4">
                <div class="flex size-14 items-center justify-center rounded-full bg-zinc-200 text-lg font-bold text-zinc-800 dark:bg-zinc-700 dark:text-zinc-100">
                    {{ $user->initials() }}
                </div>
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-zinc-900 dark:text-white" data-test="customer-name">{{ $user->name }}</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400" data-test="customer-email">{{ $user->email }}</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                @if ($user->is_active)
                    <span class="inline-flex items-center rounded-full bg-green-50 px-3 py-1 text-xs font-medium text-green-700 dark:bg-green-950/50 dark:text-green-400 border border-green-200 dark:border-green-800">
                        Active Account
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400 border border-zinc-300 dark:border-zinc-700">
                        Inactive Account
                    </span>
                @endif

                <button wire:click="toggleActive"
                        type="button"
                        class="rounded-lg px-3.5 py-2 text-xs font-semibold shadow-xs transition {{ $user->is_active ? 'bg-red-600 text-white hover:bg-red-700 dark:bg-red-500 dark:hover:bg-red-600' : 'bg-green-600 text-white hover:bg-green-700 dark:bg-green-500 dark:hover:bg-green-600' }}">
                    {{ $user->is_active ? 'Deactivate Customer' : 'Activate Customer' }}
                </button>
            </div>
        </div>

        <div class="p-6">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">Account Information</h3>
            <dl class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-lg border border-zinc-100 bg-zinc-50/50 p-4 dark:border-zinc-800/80 dark:bg-zinc-800/30">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Customer ID</dt>
                    <dd class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">#{{ $user->id }}</dd>
                </div>

                <div class="rounded-lg border border-zinc-100 bg-zinc-50/50 p-4 dark:border-zinc-800/80 dark:bg-zinc-800/30">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Full Name</dt>
                    <dd class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ $user->name }}</dd>
                </div>

                <div class="rounded-lg border border-zinc-100 bg-zinc-50/50 p-4 dark:border-zinc-800/80 dark:bg-zinc-800/30">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Email Address</dt>
                    <dd class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">{{ $user->email }}</dd>
                </div>

                <div class="rounded-lg border border-zinc-100 bg-zinc-50/50 p-4 dark:border-zinc-800/80 dark:bg-zinc-800/30">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Email Verified At</dt>
                    <dd class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ $user->email_verified_at?->format('M j, Y H:i:s') ?? 'Not verified' }}
                    </dd>
                </div>

                <div class="rounded-lg border border-zinc-100 bg-zinc-50/50 p-4 dark:border-zinc-800/80 dark:bg-zinc-800/30">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Registered On</dt>
                    <dd class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ $user->created_at?->format('M j, Y H:i:s') ?? '—' }}
                    </dd>
                </div>

                <div class="rounded-lg border border-zinc-100 bg-zinc-50/50 p-4 dark:border-zinc-800/80 dark:bg-zinc-800/30">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">Last Updated</dt>
                    <dd class="mt-1 text-sm font-semibold text-zinc-900 dark:text-white">
                        {{ $user->updated_at?->format('M j, Y H:i:s') ?? '—' }}
                    </dd>
                </div>
            </dl>
        </div>
    </div>
</div>

