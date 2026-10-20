<?php

use App\Models\Admin;
use Database\Seeders\AdminSeeder;
use Illuminate\Support\Facades\Hash;

test('admin routes support custom ADMIN_PATH prefix', function () {
    putenv('ADMIN_PATH=super-secret-panel');
    $_ENV['ADMIN_PATH'] = 'super-secret-panel';
    $this->refreshApplication();

    expect(route('admin.login'))->toContain('/super-secret-panel/login')
        ->and(route('admin.dashboard'))->toContain('/super-secret-panel');

    putenv('ADMIN_PATH=admin');
    $_ENV['ADMIN_PATH'] = 'admin';
    $this->refreshApplication();
});

test('no admin view or component contains a hardcoded /admin URL', function () {
    $files = array_merge(
        glob(resource_path('views/pages/admin/*.blade.php')) ?: [],
        glob(resource_path('views/layouts/admin*.blade.php')) ?: [],
    );

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $content = (string) file_get_contents($file);
        expect($content)->not->toContain("'/admin")
            ->and($content)->not->toContain('"/admin')
            ->and($content)->not->toContain('href="/admin')
            ->and($content)->not->toContain("href='/admin")
            ->and($content)->not->toContain('action="/admin')
            ->and($content)->not->toContain("action='/admin");
    }
});

test('admin:create artisan command creates an admin', function () {
    $this->artisan('admin:create')
        ->expectsQuestion('Name', 'CLI Admin')
        ->expectsQuestion('Email', 'cli@example.com')
        ->expectsQuestion('Password', 'cli-secret-pass')
        ->assertSuccessful();

    $admin = Admin::where('email', 'cli@example.com')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('CLI Admin')
        ->and($admin->is_active)->toBeTrue()
        ->and(Hash::check('cli-secret-pass', $admin->password))->toBeTrue();
});

test('AdminSeeder runs only in local environment', function () {
    expect(app()->isLocal())->toBeFalse();

    $this->seed(AdminSeeder::class);

    expect(Admin::where('email', 'admin@example.com')->exists())->toBeFalse();
});
