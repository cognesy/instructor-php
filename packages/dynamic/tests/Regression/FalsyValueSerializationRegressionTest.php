<?php

declare(strict_types=1);

use Cognesy\Dynamic\Field;
use Cognesy\Dynamic\Structure;

it('accepts required falsy scalar values during deserialization', function (): void {
    $structure = Structure::define('FalsyRequired', [
        Field::int('count')->required(),
        Field::bool('enabled')->required(),
        Field::string('code')->required(),
    ]);

    $structure->fromArray([
        'count' => 0,
        'enabled' => false,
        'code' => '0',
    ]);

    expect($structure->count)->toBe(0)
        ->and($structure->enabled)->toBeFalse()
        ->and($structure->code)->toBe('0');
});

it('preserves optional falsy scalar values during deserialization and serialization', function (): void {
    $structure = Structure::define('FalsyOptional', [
        Field::int('count')->optional(),
        Field::bool('enabled')->optional(),
        Field::string('code')->optional(),
    ]);

    $structure->fromArray([
        'count' => 0,
        'enabled' => false,
        'code' => '0',
    ]);

    expect($structure->count)->toBe(0)
        ->and($structure->enabled)->toBeFalse()
        ->and($structure->code)->toBe('0')
        ->and($structure->toArray())->toBe([
            'count' => 0,
            'enabled' => false,
            'code' => '0',
        ]);
});
