<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

test('admin login screen can be rendered', function () {
    $response = $this->get(route('admin.login'));

    $response->assertOk();
});

test('admin can authenticate using livewire login', function () {
    $admin = Admin::factory()->create([
        'email' => 'admin@test.com',
        'password' => 'password123',
    ]);

    Livewire::test('pages::admin.login')
        ->set('email', 'admin@test.com')
        ->set('password', 'password123')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('admin.dashboard'));

    expect(Auth::guard('admin')->check())->toBeTrue()
        ->and(Auth::guard('admin')->id())->toBe($admin->id);
});

test('admin cannot authenticate with invalid password', function () {
    Admin::factory()->create([
        'email' => 'admin@test.com',
        'password' => 'password123',
    ]);

    Livewire::test('pages::admin.login')
        ->set('email', 'admin@test.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);

    expect(Auth::guard('admin')->check())->toBeFalse();
});

test('inactive admin is blocked from logging in', function () {
    Admin::factory()->inactive()->create([
        'email' => 'inactive@test.com',
        'password' => 'password123',
    ]);

    Livewire::test('pages::admin.login')
        ->set('email', 'inactive@test.com')
        ->set('password', 'password123')
        ->call('login')
        ->assertHasErrors(['email']);

    expect(Auth::guard('admin')->check())->toBeFalse();
});

test('admin login is rate limited after 5 attempts', function () {
    Admin::factory()->create([
        'email' => 'rate-limit@test.com',
        'password' => 'password123',
    ]);

    RateLimiter::clear('rate-limit@test.com|127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        Livewire::test('pages::admin.login')
            ->set('email', 'rate-limit@test.com')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['email']);
    }

    Livewire::test('pages::admin.login')
        ->set('email', 'rate-limit@test.com')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors(['email']);
});

test('admin can log out', function () {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    expect(Auth::guard('admin')->check())->toBeFalse();
});

test('admin logout only logs out admin guard and keeps customer logged in', function () {
    $customer = User::factory()->create();
    $admin = Admin::factory()->create();

    $this->actingAs($customer, 'web');
    $this->actingAs($admin, 'admin');

    expect(Auth::guard('web')->check())->toBeTrue()
        ->and(Auth::guard('admin')->check())->toBeTrue();

    $response = $this->post(route('admin.logout'));

    $response->assertRedirect(route('admin.login'));
    expect(Auth::guard('admin')->check())->toBeFalse()
        ->and(Auth::guard('web')->check())->toBeTrue();
});

test('admin guests are redirected to admin login', function () {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.login'));
});

test('customers cannot access admin pages and are redirected to admin login', function () {
    $customer = User::factory()->create();

    $response = $this->actingAs($customer, 'web')->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.login'));
});

test('authenticated admin visiting admin login redirects to admin dashboard', function () {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.login'));

    $response->assertRedirect(route('admin.dashboard'));
});

