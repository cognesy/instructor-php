<?php declare(strict_types=1);

use Cognesy\Schema\Data\TypeDetails;

// Guards regression from instructor-a1ig (unsupported runtime values: null/resources).
it('handles null value without throwing and returns mixed type', function () {
    $type = TypeDetails::fromValue(null);

    expect($type->type())->toBe(TypeDetails::PHP_MIXED);
});

it('handles open resource value without throwing and returns mixed type', function () {
    $resource = fopen('php://temp', 'r+');
    if ($resource === false) {
        throw new RuntimeException('Failed to open test resource');
    }

    $type = TypeDetails::fromValue($resource);
    fclose($resource);

    expect($type->type())->toBe(TypeDetails::PHP_MIXED);
});

it('handles closed resource value without throwing and returns mixed type', function () {
    $resource = fopen('php://temp', 'r+');
    if ($resource === false) {
        throw new RuntimeException('Failed to open test resource');
    }
    fclose($resource);

    $type = TypeDetails::fromValue($resource);

    expect($type->type())->toBe(TypeDetails::PHP_MIXED);
});

// Guards regression from instructor-el45 (unsupported homogeneous array elements).
it('handles array of nulls without throwing and falls back to untyped array', function () {
    $type = TypeDetails::fromValue([null, null]);

    expect($type->type())->toBe(TypeDetails::PHP_ARRAY);
});

it('handles array of resources without throwing and falls back to untyped array', function () {
    $r1 = fopen('php://temp', 'r+');
    $r2 = fopen('php://temp', 'r+');
    if ($r1 === false || $r2 === false) {
        throw new RuntimeException('Failed to open test resource');
    }

    $type = TypeDetails::fromValue([$r1, $r2]);
    fclose($r1);
    fclose($r2);

    expect($type->type())->toBe(TypeDetails::PHP_ARRAY);
});

it('handles mixed unsupported array values without throwing and falls back to untyped array', function () {
    $resource = fopen('php://temp', 'r+');
    if ($resource === false) {
        throw new RuntimeException('Failed to open test resource');
    }

    $type = TypeDetails::fromValue([null, $resource]);
    fclose($resource);

    expect($type->type())->toBe(TypeDetails::PHP_ARRAY);
});
