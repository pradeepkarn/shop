<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Admin Profile')] class extends Component {
    public string $name = '';
    public string $email = '';

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $this->name = $admin->name;
        $this->email = $admin->email;
    }

    public function updateProfile(): void
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
        ]);

        $admin->update([
            'name' => $this->name,
            'email' => $this->email,
        ]);

        session()->flash('profile-status', 'Profile updated successfully.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password:admin'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $admin->update([
            'password' => Hash::make($this->password),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        session()->flash('password-status', 'Password updated successfully.');
    }
}; ?>

<div class="space-y-8">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Admin Profile</h2>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Update your account profile information and password.</p>
    </div>

    <!-- Profile Information Section -->
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="max-w-xl">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Profile Details</h3>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Update your name and primary email address.</p>

            @if (session('profile-status'))
                <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300">
                    {{ session('profile-status') }}
                </div>
            @endif

            <form wire:submit="updateProfile" class="mt-6 space-y-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Name</label>
                    <input wire:model="name"
                           type="text"
                           id="name"
                           required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-zinc-400 dark:focus:ring-zinc-400" />
                    @error('name')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="profile-email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Email address</label>
                    <input wire:model="email"
                           type="email"
                           id="profile-email"
                           required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-zinc-400 dark:focus:ring-zinc-400" />
                    @error('email')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        data-test="update-admin-profile-button"
                        class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                    <span wire:loading.remove wire:target="updateProfile">Save Profile</span>
                    <span wire:loading wire:target="updateProfile">Saving...</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Password Update Section -->
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
        <div class="max-w-xl">
            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">Update Password</h3>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Ensure your account is using a long, random password to stay secure.</p>

            @if (session('password-status'))
                <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300">
                    {{ session('password-status') }}
                </div>
            @endif

            <form wire:submit="updatePassword" class="mt-6 space-y-5">
                <div>
                    <label for="current_password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Current Password</label>
                    <input wire:model="current_password"
                           type="password"
                           id="current_password"
                           required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-zinc-400 dark:focus:ring-zinc-400" />
                    @error('current_password')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">New Password</label>
                    <input wire:model="password"
                           type="password"
                           id="new_password"
                           required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-zinc-400 dark:focus:ring-zinc-400" />
                    @error('password')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Confirm Password</label>
                    <input wire:model="password_confirmation"
                           type="password"
                           id="password_confirmation"
                           required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-zinc-400 dark:focus:ring-zinc-400" />
                    @error('password_confirmation')
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        data-test="update-admin-password-button"
                        class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-xs transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                    <span wire:loading.remove wire:target="updatePassword">Update Password</span>
                    <span wire:loading wire:target="updatePassword">Updating...</span>
                </button>
            </form>
        </div>
    </div>
</div>

