<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;

test('inactive customer cannot log in via fortify', function () {
    $user = User::factory()->create([
        'email' => 'inactive.customer@example.com',
        'password' => 'password',
        'is_active' => false,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'inactive.customer@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors();
    expect(Auth::guard('web')->check())->toBeFalse();
});

test('active customer can log in via fortify', function () {
    $user = User::factory()->create([
        'email' => 'active.customer@example.com',
        'password' => 'password',
        'is_active' => true,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'active.customer@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors();
    expect(Auth::guard('web')->check())->toBeTrue()
        ->and(Auth::guard('web')->id())->toBe($user->id);
});

test('inactive customer is logged out by middleware on protected customer routes', function () {
    $user = User::factory()->create([
        'is_active' => false,
    ]);

    $this->actingAs($user, 'web');

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email']);

    expect(Auth::guard('web')->check())->toBeFalse();
});

test('active customer is not logged out by middleware on protected customer routes', function () {
    $user = User::factory()->create([
        'is_active' => true,
    ]);

    $this->actingAs($user, 'web');

    $response = $this->get(route('dashboard'));

    $response->assertOk();
    expect(Auth::guard('web')->check())->toBeTrue();
});
