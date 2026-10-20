<?php

use App\Services\UpidGenerator;

test('upid generator produces KHP prefix and exactly 15 characters', function () {
    $upid = UpidGenerator::generate();

    expect($upid)->toStartWith('KHP')
        ->and(strlen($upid))->toBe(15);
});

test('upid charset strictly excludes 0, O, 1, I', function () {
    for ($i = 0; $i < 50; $i++) {
        $upid = UpidGenerator::generate();
        $randomPart = substr($upid, 3);

        expect($randomPart)->not->toContain('0')
            ->not->toContain('O')
            ->not->toContain('1')
            ->not->toContain('I')
            ->toMatch('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{12}$/');
    }
});
