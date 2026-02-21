<?php declare(strict_types=1);

use Cognesy\Utils\Identifier\OpaqueExternalId;

final readonly class TestExternalId extends OpaqueExternalId {}
final readonly class OtherExternalId extends OpaqueExternalId {}

it('rejects empty external id', function () {
    new TestExternalId('');
})->throws(\InvalidArgumentException::class);

it('supports fromString and string conversion', function () {
    $id = TestExternalId::fromString('call_123');

    expect($id->toString())->toBe('call_123')
        ->and((string) $id)->toBe('call_123');
});

it('compares equality by type and value', function () {
    $a = new TestExternalId('call_1');
    $b = new TestExternalId('call_1');
    $c = new OtherExternalId('call_1');

    expect($a->equals($b))->toBeTrue()
        ->and($a->equals($c))->toBeFalse();
});
