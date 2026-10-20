<?php

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.seller')] #[Title('Store Profile')] class extends Component {
    use WithFileUploads;

    public string $store_name = '';
    public string $store_slug = '';
    public string $pan = '';
    public string $business_type = '';
    public string $description = '';

    public string $support_email = '';
    public string $support_phone = '';
    public string $pickup_address_line1 = '';
    public string $pickup_address_line2 = '';
    public string $pickup_city = '';
    public string $pickup_state = '';
    public string $pickup_postal_code = '';
    public string $pickup_country = 'India';

    public mixed $logo = null;
    public mixed $banner = null;
    public ?string $existing_logo_path = null;
    public ?string $existing_banner_path = null;

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $p = $user->sellerProfile;

        if (! $p) {
            $this->redirect(route('seller.apply'), navigate: true);

            return;
        }

        $this->store_name = $p->store_name;
        $this->store_slug = $p->store_slug;
        $this->pan = $p->pan;
        $this->business_type = $p->business_type;
        $this->description = $p->description ?? '';
        $this->existing_logo_path = $p->logo_path;
        $this->existing_banner_path = $p->banner_path;

        $this->support_email = $p->support_email;
        $this->support_phone = $p->support_phone;
        $this->pickup_address_line1 = $p->pickup_address_line1;
        $this->pickup_address_line2 = $p->pickup_address_line2 ?? '';
        $this->pickup_city = $p->pickup_city;
        $this->pickup_state = $p->pickup_state;
        $this->pickup_postal_code = $p->pickup_postal_code;
        $this->pickup_country = $p->pickup_country ?: 'India';
    }

    public function updateStoreProfile(): void
    {
        $this->validate([
            'store_name' => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'support_email' => ['required', 'string', 'email', 'max:255'],
            'support_phone' => ['required', 'string', 'max:20'],
            'pickup_address_line1' => ['required', 'string', 'max:255'],
            'pickup_address_line2' => ['nullable', 'string', 'max:255'],
            'pickup_city' => ['required', 'string', 'max:100'],
            'pickup_state' => ['required', 'string', 'max:100'],
            'pickup_postal_code' => ['required', 'string', 'max:20'],
            'pickup_country' => ['required', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        /** @var SellerProfile $profile */
        $profile = $user->sellerProfile;

        $logoPath = $profile->logo_path;
        if ($this->logo) {
            if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                Storage::disk('public')->delete($logoPath);
            }
            $logoPath = $this->logo->store('seller-logos', 'public');
            $this->existing_logo_path = $logoPath;
            $this->logo = null;
        }

        $bannerPath = $profile->banner_path;
        if ($this->banner) {
            if ($bannerPath && Storage::disk('public')->exists($bannerPath)) {
                Storage::disk('public')->delete($bannerPath);
            }
            $bannerPath = $this->banner->store('seller-banners', 'public');
            $this->existing_banner_path = $bannerPath;
            $this->banner = null;
        }

        // store_slug and pan are strictly NOT updated here as they are read-only after approval
        $profile->update([
            'store_name' => $this->store_name,
            'description' => $this->description,
            'support_email' => $this->support_email,
            'support_phone' => $this->support_phone,
            'pickup_address_line1' => $this->pickup_address_line1,
            'pickup_address_line2' => $this->pickup_address_line2,
            'pickup_city' => $this->pickup_city,
            'pickup_state' => $this->pickup_state,
            'pickup_postal_code' => $this->pickup_postal_code,
            'pickup_country' => $this->pickup_country,
            'logo_path' => $logoPath,
            'banner_path' => $bannerPath,
        ]);

        session()->flash('status', 'Store profile information updated successfully.');
    }
}; ?>

<div class="space-y-6">
    <div>
        <h2 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">Store Profile</h2>
        <p class="text-sm text-zinc-500 dark:text-zinc-400">Manage your store appearance, contact information, and warehouse pickup location.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-300">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="updateStoreProfile" class="space-y-6">
        <!-- Brand & Store Identity -->
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Store Identity</h3>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Store slug and PAN card cannot be changed once approved.</p>

            <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="store_name" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Store Name *</label>
                    <input wire:model="store_name" type="text" id="store_name" required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    @error('store_name') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="store_slug" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Store URL Slug (Read-Only)</label>
                    <input wire:model="store_slug" type="text" id="store_slug" disabled readonly
                           class="mt-1 block w-full rounded-lg border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm text-zinc-500 cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-400" />
                    <p class="mt-1 text-xs text-zinc-400">Locked after approval</p>
                </div>

                <div>
                    <label for="pan" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">PAN Card (Read-Only)</label>
                    <input wire:model="pan" type="text" id="pan" disabled readonly
                           class="mt-1 block w-full rounded-lg border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm text-zinc-500 cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-400 uppercase" />
                    <p class="mt-1 text-xs text-zinc-400">Locked after approval</p>
                </div>

                <div>
                    <label for="business_type" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Business Type</label>
                    <input wire:model="business_type" type="text" id="business_type" disabled readonly
                           class="mt-1 block w-full capitalize rounded-lg border border-zinc-200 bg-zinc-100 px-3 py-2 text-sm text-zinc-500 cursor-not-allowed dark:border-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-400" />
                </div>

                <div class="sm:col-span-2">
                    <label for="description" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Store Description</label>
                    <textarea wire:model="description" id="description" rows="3"
                              class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white"></textarea>
                    @error('description') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Media: Logo & Banner -->
            <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 pt-6 border-t border-zinc-100 dark:border-zinc-800">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Store Logo</label>
                    @if ($logo)
                        <div class="mt-2 flex items-center gap-3">
                            <img src="{{ $logo->temporaryUrl() }}" alt="New Logo Preview" class="size-14 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700" />
                            <span class="text-xs text-zinc-500">New logo selected</span>
                        </div>
                    @elseif ($existing_logo_path)
                        <div class="mt-2 flex items-center gap-3">
                            <img src="{{ Storage::disk('public')->url($existing_logo_path) }}" alt="Logo" class="size-14 rounded-lg object-cover border border-zinc-200 dark:border-zinc-700" />
                        </div>
                    @endif
                    <input wire:model="logo" type="file" accept="image/*" class="mt-2 block w-full text-xs text-zinc-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-300" />
                    @error('logo') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Store Banner</label>
                    @if ($banner)
                        <div class="mt-2">
                            <img src="{{ $banner->temporaryUrl() }}" alt="New Banner Preview" class="h-14 w-full max-w-xs rounded-lg object-cover border border-zinc-200 dark:border-zinc-700" />
                            <span class="text-xs text-zinc-500">New banner selected</span>
                        </div>
                    @elseif ($existing_banner_path)
                        <div class="mt-2">
                            <img src="{{ Storage::disk('public')->url($existing_banner_path) }}" alt="Banner" class="h-14 w-full max-w-xs rounded-lg object-cover border border-zinc-200 dark:border-zinc-700" />
                        </div>
                    @endif
                    <input wire:model="banner" type="file" accept="image/*" class="mt-2 block w-full text-xs text-zinc-500 file:mr-4 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-300" />
                    @error('banner') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- Contact & Pickup Address -->
        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-800 dark:bg-zinc-900">
            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">Contact & Pickup Location</h3>
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">Keep support contact and shipment pickup address up to date.</p>

            <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="support_email" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Support Email *</label>
                    <input wire:model="support_email" type="email" id="support_email" required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    @error('support_email') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="support_phone" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Support Phone *</label>
                    <input wire:model="support_phone" type="text" id="support_phone" required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    @error('support_phone') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="pickup_address_line1" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Address Line 1 *</label>
                    <input wire:model="pickup_address_line1" type="text" id="pickup_address_line1" required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    @error('pickup_address_line1') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="pickup_address_line2" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Address Line 2 (Optional)</label>
                    <input wire:model="pickup_address_line2" type="text" id="pickup_address_line2"
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    @error('pickup_address_line2') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="pickup_city" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">City *</label>
                    <input wire:model="pickup_city" type="text" id="pickup_city" required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    @error('pickup_city') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="pickup_state" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">State *</label>
                    <input wire:model="pickup_state" type="text" id="pickup_state" required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    @error('pickup_state') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="pickup_postal_code" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Postal / PIN Code *</label>
                    <input wire:model="pickup_postal_code" type="text" id="pickup_postal_code" required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-hidden focus:ring-1 focus:ring-zinc-500 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    @error('pickup_postal_code') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="pickup_country" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">Country *</label>
                    <input wire:model="pickup_country" type="text" id="pickup_country" required
                           class="mt-1 block w-full rounded-lg border border-zinc-300 bg-zinc-100 px-3 py-2 text-sm text-zinc-900 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white" />
                    @error('pickup_country') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" data-test="save-store-profile-button" class="rounded-lg bg-zinc-900 px-6 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100 transition">
                <span wire:loading.remove wire:target="updateStoreProfile">Save Store Profile</span>
                <span wire:loading wire:target="updateStoreProfile">Saving...</span>
            </button>
        </div>
    </form>
</div>

