<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('admin can view profile page', function () {
    $admin = Admin::factory()->create([
        'name' => 'Profile Boss',
        'email' => 'boss@example.com',
    ]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.profile'));

    $response->assertOk()
        ->assertSee('Admin Profile')
        ->assertSee('Profile Details')
        ->assertSee('Update Password');
});

test('admin can update profile details', function () {
    $admin = Admin::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test('pages::admin.profile')
        ->set('name', 'Updated Name')
        ->set('email', 'updated@example.com')
        ->call('updateProfile')
        ->assertHasNoErrors();

    $freshAdmin = $admin->fresh();
    expect($freshAdmin->name)->toBe('Updated Name')
        ->and($freshAdmin->email)->toBe('updated@example.com');
});

test('admin can update password', function () {
    $admin = Admin::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    Livewire::actingAs($admin, 'admin')
        ->test('pages::admin.profile')
        ->set('current_password', 'old-password')
        ->set('password', 'new-password-123')
        ->set('password_confirmation', 'new-password-123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password-123', $admin->fresh()->password))->toBeTrue();
});

