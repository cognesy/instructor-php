<?php declare(strict_types=1);

use Cognesy\Dynamic\CallableSchemaFactory;
use Cognesy\Schema\Data\ObjectSchema;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

function dynamicCallableFactorySample(string $name, int $count = 1): void {}

it('creates schema from function signature', function () {
    $schema = (new CallableSchemaFactory())->fromFunctionName('dynamicCallableFactorySample');

    expect($schema)->toBeInstanceOf(ObjectSchema::class)
        ->and($schema->hasProperty('name'))->toBeTrue()
        ->and($schema->hasProperty('count'))->toBeTrue()
        ->and($schema->required)->toContain('name')
        ->and($schema->required)->not->toContain('count');
});

it('creates schema from callable with custom name', function () {
    $callable = static fn(string $query, bool $strict = false) => null;

    $schema = (new CallableSchemaFactory())->fromCallable($callable, 'search_args');

    expect($schema->name())->toBe('search_args')
        ->and($schema->hasProperty('query'))->toBeTrue()
        ->and($schema->hasProperty('strict'))->toBeTrue();
});

it('accepts optional type resolver injection', function () {
    $resolver = TypeResolver::create();

    $schema = (new CallableSchemaFactory(resolver: $resolver))
        ->fromFunctionName('dynamicCallableFactorySample');

    expect($schema)->toBeInstanceOf(ObjectSchema::class)
        ->and($schema->hasProperty('name'))->toBeTrue()
        ->and($schema->hasProperty('count'))->toBeTrue();
});
