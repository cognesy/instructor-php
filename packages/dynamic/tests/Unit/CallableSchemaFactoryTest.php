<?php declare(strict_types=1);

use Cognesy\Schema\CallableSchemaFactory;
use Cognesy\Schema\Data\ObjectSchema;
use Cognesy\Schema\TypeInfo;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

function dynamicCallableFactorySample(string $name, int $count = 1): void {}
function dynamicCallableFactoryVariadicSample(mixed ...$tags): void {}

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

it('does not double-wrap variadic parameters when resolver already returns collection type', function () {
    $resolver = TypeResolver::create([
        \ReflectionParameter::class => new class implements TypeResolverInterface {
            public function resolve(mixed $subject, ?TypeContext $typeContext = null): Type {
                return Type::list(Type::string());
            }
        },
    ]);

    $schema = (new CallableSchemaFactory(resolver: $resolver))
        ->fromFunctionName('dynamicCallableFactoryVariadicSample');
    $tagsType = $schema->getPropertySchema('tags')->type();
    $nestedType = TypeInfo::collectionValueType($tagsType);

    expect((string) $tagsType)->toBe((string) Type::list(Type::string()))
        ->and($nestedType?->isIdentifiedBy(TypeIdentifier::STRING))->toBeTrue();
});
