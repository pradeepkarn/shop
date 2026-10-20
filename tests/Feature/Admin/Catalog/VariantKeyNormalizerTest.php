<?php

use App\Services\VariantKeyNormalizer;

test('variant key normalizer sorts keys alphabetically and slugifies', function () {
    $attributes = [
        'Size' => 'XL',
        'Color' => 'Navy Blue',
    ];

    $key = VariantKeyNormalizer::normalize($attributes);

    expect($key)->toBe('color:navy-blue|size:xl');
});

test('variant key normalizer ignores empty and null attributes', function () {
    $attributes = [
        'RAM' => '8 GB',
        'EmptyField' => '',
        'NullField' => null,
        'Storage' => '256 GB',
    ];

    $key = VariantKeyNormalizer::normalize($attributes);

    expect($key)->toBe('ram:8-gb|storage:256-gb');
});
