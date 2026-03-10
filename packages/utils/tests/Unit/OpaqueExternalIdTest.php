<?php declare(strict_types=1);

use Cognesy\Utils\Identifier\OpaqueExternalId;

final readonly class TestExternalId extends OpaqueExternalId {}
final readonly class OtherExternalId extends OpaqueExternalId {}

it('allows empty external id', function () {
    $id = new TestExternalId('');

    expect($id->isEmpty())->toBeTrue()
        ->and($id->toString())->toBe('')
        ->and($id->toNullableString())->toBeNull();
});

it('creates null id via factory', function () {
    $id = TestExternalId::null();

    expect($id->isEmpty())->toBeTrue()
        ->and($id->toString())->toBe('');
});

it('creates empty id via explicit factory', function () {
    $id = TestExternalId::empty();

    expect($id->isEmpty())->toBeTrue()
        ->and($id->isPresent())->toBeFalse()
        ->and($id->toString())->toBe('');
});

it('non-empty id is not empty', function () {
    $id = new TestExternalId('call_123');

    expect($id->isEmpty())->toBeFalse()
        ->and($id->isPresent())->toBeTrue()
        ->and($id->toNullableString())->toBe('call_123');
});

it('supports fromString and string conversion', function () {
    $id = TestExternalId::fromString('call_123');

    expect($id->toString())->toBe('call_123')
        ->and((string) $id)->toBe('call_123');
});

it('compares equality by type and value', function () {
    $a = new TestExternalId('call_1');
    $b = new TestExternalId('call_1');
    $c = new OtherExternalId('call_1');
    $emptyA = TestExternalId::empty();
    $emptyB = TestExternalId::null();
    $emptyOther = OtherExternalId::empty();

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse()
        ->and($emptyA->equals($emptyB))->toBeTrue()
        ->and($emptyA->equals($emptyOther))->toBeFalse();
});
