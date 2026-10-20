<?php

use App\Models\Admin;
use App\Models\User;

test('authenticated admin can view dashboard', function () {
    $admin = Admin::factory()->create();

    $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Total Customers')
        ->assertSee('New Customers This Week');
});

test('dashboard displays customer counts', function () {
    $admin = Admin::factory()->create();

    // Past customers
    User::factory()->create(['created_at' => now()->subWeeks(2)]);
    User::factory()->create(['created_at' => now()->subWeeks(3)]);

    // New customers this week
    User::factory()->create(['created_at' => now()]);

    $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSee('3') // total
        ->assertSee('1'); // this week
});

