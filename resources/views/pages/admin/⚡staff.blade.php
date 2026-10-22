<?php

use App\Mail\StaffInvitationMail;
use App\Models\Admin;
use App\Services\AdminActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

new #[Layout('layouts.admin')] #[Title('Staff Management')] class extends Component {
    public string $search = '';
    public string $statusFilter = 'all';

    // Modal state
    public bool $showStaffModal = false;
    public ?int $editingAdminId = null;
    public string $name = '';
    public string $email = '';
    public array $selectedRoles = [];

    public function mount(): void
    {
        $this->authorize('admins.view');
    }

    public function openInviteModal(): void
    {
        $this->authorize('admins.manage');

        $this->reset(['editingAdminId', 'name', 'email', 'selectedRoles']);
        $this->resetErrorBag();
        $this->showStaffModal = true;
    }

    public function openEditModal(int $adminId): void
    {
        $this->authorize('admins.manage');

        /** @var Admin $currentAdmin */
        $currentAdmin = Auth::guard('admin')->user();

        // Safeguard: Staff cannot edit their own roles or access via staff screen
        if ($currentAdmin->id === $adminId) {
            $this->addError('staff', 'You cannot edit your own account roles here. Use the Profile screen to edit your personal info.');

            return;
        }

        $target = Admin::findOrFail($adminId);

        // Safeguard: Cannot manage staff whose permissions exceed own authority or who hold Super Admin
        if (! $currentAdmin->canManageStaff($target)) {
            $this->addError('staff', 'You cannot manage a staff member who holds permissions or roles outside your own authority.');

            return;
        }

        $this->editingAdminId = $target->id;
        $this->name = $target->name;
        $this->email = $target->email;
        $this->selectedRoles = $target->roles->pluck('name')->all();

        $this->resetErrorBag();
        $this->showStaffModal = true;
    }

    public function saveStaff(): void
    {
        $this->authorize('admins.manage');

        /** @var Admin $currentAdmin */
        $currentAdmin = Auth::guard('admin')->user();

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                $this->editingAdminId ? 'unique:admins,email,'.$this->editingAdminId : 'unique:admins,email',
            ],
            'selectedRoles' => ['array'],
        ]);

        // Safeguard 1: Prevent editing self
        if ($this->editingAdminId && $currentAdmin->id === $this->editingAdminId) {
            $this->addError('staff', 'You cannot modify your own roles or permissions.');

            return;
        }

        // Safeguard 2: Escalation Prevention: Only Super Admins can assign or remove Super Admin role
        if (! $currentAdmin->isSuperAdmin()) {
            if (in_array('Super Admin', $this->selectedRoles, true)) {
                $this->addError('selectedRoles', 'Privilege Escalation Blocked: Only Super Admins can assign the Super Admin role.');

                return;
            }

            // Check if removing Super Admin from target
            if ($this->editingAdminId) {
                $target = Admin::find($this->editingAdminId);
                if ($target && $target->hasRole('Super Admin') && ! in_array('Super Admin', $this->selectedRoles, true)) {
                    $this->addError('selectedRoles', 'Privilege Escalation Blocked: Only Super Admins can remove the Super Admin role.');

                    return;
                }
            }

            // Check each assigned role is a subset of current admin's permissions
            foreach ($this->selectedRoles as $roleName) {
                $role = Role::where('name', $roleName)->where('guard_name', 'admin')->first();
                if ($role && ! $currentAdmin->canAssignRole($role)) {
                    $this->addError('selectedRoles', "Privilege Escalation Blocked: You cannot assign role '{$roleName}' as it contains permissions outside your authority.");

                    return;
                }
            }
        }

        // Safeguard 3: Last active Super Admin demotion protection
        if ($this->editingAdminId) {
            $target = Admin::find($this->editingAdminId);
            if ($target && $target->is_active && $target->hasRole('Super Admin') && ! in_array('Super Admin', $this->selectedRoles, true)) {
                $activeSuperAdminCount = Admin::role('Super Admin')->where('is_active', true)->count();
                if ($activeSuperAdminCount <= 1) {
                    $this->addError('selectedRoles', 'Protected: Cannot remove the Super Admin role from the last active Super Administrator.');

                    return;
                }
            }
        }

        if ($this->editingAdminId) {
            $target = Admin::findOrFail($this->editingAdminId);

            if (! $currentAdmin->canManageStaff($target)) {
                $this->addError('staff', 'You cannot modify a staff member who holds permissions or roles outside your own authority.');

                return;
            }

            $rolesBefore = $target->roles->pluck('name')->all();

            $target->update([
                'name' => $this->name,
                'email' => $this->email,
            ]);

            $target->syncRoles($this->selectedRoles);

            AdminActivityLogger::log('staff.updated', $target, [
                'name' => $this->name,
                'email' => $this->email,
                'roles_before' => $rolesBefore,
                'roles_after' => $this->selectedRoles,
            ]);

            session()->flash('status', "Staff member [{$target->email}] updated successfully.");
        } else {
            // New Staff Invitation
            $newAdmin = Admin::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make(Str::random(32)),
                'is_active' => true,
                'invitation_accepted_at' => null,
            ]);

            $newAdmin->syncRoles($this->selectedRoles);

            // Generate set-password token and send email
            $token = Password::broker('admins')->createToken($newAdmin);
            Mail::to($newAdmin->email)->queue(new StaffInvitationMail($newAdmin, $token));

            AdminActivityLogger::log('staff.invited', $newAdmin, [
                'email' => $newAdmin->email,
                'roles' => $this->selectedRoles,
            ]);

            session()->flash('status', "Invitation email sent to [{$newAdmin->email}] with set-password link.");
        }

        // Flush permission cache
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->showStaffModal = false;
        $this->reset(['editingAdminId', 'name', 'email', 'selectedRoles']);
    }

    public function resendInvite(int $adminId): void
    {
        $this->authorize('admins.manage');

        /** @var Admin $currentAdmin */
        $currentAdmin = Auth::guard('admin')->user();

        $admin = Admin::findOrFail($adminId);

        if (! $currentAdmin->canManageStaff($admin)) {
            session()->flash('error', 'You cannot resend invites to a staff member who holds permissions or roles outside your own authority.');

            return;
        }

        // Delete any existing tokens
        DB::table('admin_password_reset_tokens')->where('email', $admin->email)->delete();

        // Create fresh token and dispatch email
        $token = Password::broker('admins')->createToken($admin);
        Mail::to($admin->email)->queue(new StaffInvitationMail($admin, $token));

        AdminActivityLogger::log('staff.invite_resent', $admin, [
            'email' => $admin->email,
        ]);

        session()->flash('status', "Fresh invitation email sent to [{$admin->email}].");
    }

    public function toggleActive(int $adminId): void
    {
        $this->authorize('admins.manage');

        /** @var Admin $currentAdmin */
        $currentAdmin = Auth::guard('admin')->user();

        // Safeguard 1: Cannot deactivate self
        if ($currentAdmin->id === $adminId) {
            session()->flash('error', 'You cannot deactivate your own account.');

            return;
        }

        $target = Admin::findOrFail($adminId);

        // Safeguard 2: Cannot manage staff with superior permissions or Super Admin
        if (! $currentAdmin->canManageStaff($target)) {
            session()->flash('error', 'You cannot change the active status of a staff member who holds permissions or roles outside your own authority.');

            return;
        }

        // Safeguard 3: Last active Super Admin protection
        if ($target->is_active && $target->hasRole('Super Admin')) {
            $activeSuperAdminCount = Admin::role('Super Admin')->where('is_active', true)->count();
            if ($activeSuperAdminCount <= 1) {
                session()->flash('error', 'Protected: Cannot deactivate the last active Super Administrator.');

                return;
            }
        }

        $newStatus = ! $target->is_active;
        $target->update(['is_active' => $newStatus]);

        // If deactivating, delete any pending reset/invite tokens so they cannot be redeemed
        if (! $newStatus) {
            DB::table('admin_password_reset_tokens')->where('email', $target->email)->delete();
        }

        AdminActivityLogger::log($newStatus ? 'staff.activated' : 'staff.deactivated', $target, [
            'email' => $target->email,
            'is_active' => $newStatus,
        ]);

        session()->flash('status', "Account [{$target->email}] has been ".($newStatus ? 'activated.' : 'deactivated.'));
    }

    public function getStaffProperty()
    {
        return Admin::with('roles')
            ->when($this->search !== '', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->statusFilter === 'pending', fn ($q) => $q->whereNull('invitation_accepted_at'))
            ->orderByRaw('is_active DESC, id ASC')
            ->get();
    }

    public function getAvailableRolesProperty()
    {
        return Role::where('guard_name', 'admin')->orderBy('name')->get();
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2 text-xs text-zinc-500">
                <a href="{{ route('admin.dashboard') }}" wire:navigate class="hover:underline">Dashboard</a>
                <span>›</span>
                <span class="font-semibold text-zinc-900 dark:text-white">Staff Management</span>
            </div>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Admin Staff</h2>
            <p class="text-xs text-zinc-500">Invite, configure roles, and manage administrative team accounts.</p>
        </div>

        <div class="flex items-center gap-3">
            <button wire:click="openInviteModal"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-zinc-900 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                </svg>
                <span>Invite New Staff</span>
            </button>
        </div>
    </div>

    <!-- Status Alerts -->
    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-xs font-medium text-green-800 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-xs font-medium text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
            {{ session('error') }}
        </div>
    @endif

    <!-- Staff Table Container -->
    <div class="rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900 overflow-hidden">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4 border-b border-zinc-200 dark:border-zinc-800 gap-3">
            <div class="flex items-center gap-3">
                <div class="relative w-64">
                    <input wire:model.live.debounce.300ms="search"
                           type="search"
                           placeholder="Search by name or email..."
                           class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-900 placeholder-zinc-400 focus:border-zinc-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                </div>
                <select wire:model.live="statusFilter" class="rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-xs text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white">
                    <option value="all">All Statuses</option>
                    <option value="active">Active Only</option>
                    <option value="inactive">Inactive Only</option>
                    <option value="pending">Invitation Pending</option>
                </select>
            </div>
            <div class="text-xs text-zinc-500">
                {{ count($this->staff) }} staff member(s)
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-zinc-50 text-zinc-500 uppercase font-semibold dark:bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3">Member</th>
                        <th class="px-4 py-3">Assigned Roles</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Invitation</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @forelse ($this->staff as $member)
                        <tr wire:key="staff-{{ $member->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-200 text-xs font-bold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                        {{ $member->initials() }}
                                    </span>
                                    <div>
                                        <div class="font-semibold text-zinc-900 dark:text-white flex items-center gap-1.5">
                                            <span>{{ $member->name }}</span>
                                            @if ($member->id === Auth::guard('admin')->id())
                                                <span class="text-[10px] text-zinc-400 font-normal">(You)</span>
                                            @endif
                                        </div>
                                        <div class="text-zinc-400 font-mono text-[11px]">{{ $member->email }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($member->roles as $r)
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-semibold {{ $r->name === 'Super Admin' ? 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-900/60' : 'bg-zinc-100 text-zinc-700 border border-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700' }}">
                                            {{ $r->name }}
                                        </span>
                                    @empty
                                        <span class="text-zinc-400 italic text-[11px]">No roles (No access)</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($member->is_active)
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/40">
                                        Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-semibold text-zinc-500 border border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700">
                                        Deactivated
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-zinc-500 text-[11px]">
                                @if ($member->invitation_accepted_at)
                                    <span class="text-emerald-600 dark:text-emerald-400">Accepted {{ $member->invitation_accepted_at->diffForHumans() }}</span>
                                @else
                                    <div class="flex items-center gap-2">
                                        <span class="text-amber-600 dark:text-amber-400 font-medium">Pending Setup</span>
                                        <button wire:click="resendInvite({{ $member->id }})" type="button" class="text-zinc-700 underline hover:text-zinc-900 dark:text-zinc-300">
                                            Resend
                                        </button>
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($member->id !== Auth::guard('admin')->id())
                                        <button wire:click="openEditModal({{ $member->id }})"
                                                type="button"
                                                class="rounded-md border border-zinc-300 px-2.5 py-1 text-xs font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                            Edit
                                        </button>
                                        <button wire:click="toggleActive({{ $member->id }})"
                                                wire:confirm="Are you sure you want to {{ $member->is_active ? 'deactivate' : 'activate' }} this staff member?"
                                                type="button"
                                                class="rounded-md border {{ $member->is_active ? 'border-red-200 text-red-600 hover:bg-red-50 dark:border-red-900/50 dark:text-red-400 dark:hover:bg-red-950/30' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50 dark:border-emerald-900/50 dark:text-emerald-400 dark:hover:bg-emerald-950/30' }} px-2.5 py-1 text-xs font-medium">
                                            {{ $member->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    @else
                                        <span class="text-[11px] text-zinc-400 italic">Self</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-xs text-zinc-500">
                                No staff members found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Staff Invite / Edit Modal -->
    @if ($showStaffModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div wire:click="$set('showStaffModal', false)" class="fixed inset-0 bg-zinc-950/60 backdrop-blur-xs"></div>

            <div class="relative w-full max-w-lg rounded-2xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-800 dark:bg-zinc-900 space-y-6">
                <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-800 pb-4">
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">
                        {{ $editingAdminId ? 'Edit Staff Member' : 'Invite New Staff Member' }}
                    </h3>
                    <button wire:click="$set('showStaffModal', false)" type="button" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200">
                        ✕
                    </button>
                </div>

                <form wire:submit="saveStaff" class="space-y-4">
                    <div>
                        <label for="name" class="block text-xs font-semibold text-zinc-900 dark:text-white">Full Name *</label>
                        <input wire:model="name" id="name" type="text" required placeholder="e.g. Sarah Jenkins"
                               class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-xs text-zinc-900 focus:border-zinc-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        @error('name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-xs font-semibold text-zinc-900 dark:text-white">Email Address *</label>
                        <input wire:model="email" id="email" type="email" required placeholder="sarah@example.com"
                               class="mt-1 block w-full rounded-lg border border-zinc-300 px-3 py-2 text-xs text-zinc-900 focus:border-zinc-500 focus:outline-hidden dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                        @error('email')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    @error('selectedRoles')
                        <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
                            {{ $message }}
                        </div>
                    @enderror

                    <!-- Roles Checkbox Selection -->
                    <div class="space-y-2">
                        <label class="block text-xs font-semibold text-zinc-900 dark:text-white">Assign Roles (Multiple Allowed)</label>
                        <div class="space-y-2 rounded-xl border border-zinc-200 p-3 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-800/30">
                            @foreach ($this->availableRoles as $role)
                                @php
                                    /** @var Admin $currentAdmin */
                                    $currentAdmin = Auth::guard('admin')->user();
                                    $canAssign = $currentAdmin->canAssignRole($role);
                                @endphp
                                <label class="flex items-center justify-between gap-3 text-xs cursor-pointer py-1 {{ ! $canAssign ? 'opacity-50 cursor-not-allowed' : '' }}">
                                    <div class="flex items-center gap-2">
                                        <input type="checkbox"
                                               wire:model="selectedRoles"
                                               value="{{ $role->name }}"
                                               {{ ! $canAssign ? 'disabled' : '' }}
                                               class="size-3.5 rounded border-zinc-300 text-zinc-900 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800" />
                                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $role->name }}</span>
                                    </div>
                                    @if ($role->name === 'Super Admin')
                                        <span class="text-[10px] text-amber-600 dark:text-amber-400 font-bold">Master</span>
                                    @elseif (! $canAssign)
                                        <span class="text-[10px] text-zinc-400 italic">Exceeds your permissions</span>
                                    @endif
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-800">
                        <button wire:click="$set('showStaffModal', false)" type="button" class="rounded-lg border border-zinc-300 px-4 py-2 text-xs font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-zinc-900 px-5 py-2 text-xs font-semibold text-white hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                            {{ $editingAdminId ? 'Save Changes' : 'Send Invite' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

