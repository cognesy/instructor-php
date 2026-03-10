<?php declare(strict_types=1);

use Cognesy\Instructor\Data\CachedContext;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Messages\Messages;

it('keeps instructor internal request objects typed', function () {
    expect(typedInternalParameterName(CachedContext::class, '__construct', 'messages'))->toBe(Messages::class)
        ->and(typedInternalParameterAllowsNull(CachedContext::class, '__construct', 'messages'))->toBeTrue()
        ->and(typedInternalParameterName(StructuredOutputRequest::class, '__construct', 'messages'))->toBe(Messages::class)
        ->and(typedInternalParameterAllowsNull(StructuredOutputRequest::class, '__construct', 'messages'))->toBeTrue()
        ->and(typedInternalParameterName(StructuredOutputRequest::class, 'with', 'messages'))->toBe(Messages::class)
        ->and(typedInternalParameterAllowsNull(StructuredOutputRequest::class, 'with', 'messages'))->toBeTrue()
        ->and(typedInternalParameterName(StructuredOutputRequest::class, 'withMessages', 'messages'))->toBe(Messages::class);
});

function typedInternalParameterName(string $class, string $method, string $parameter): string
{
    $type = typedInternalParameterReflection($class, $method, $parameter);

    expect($type)->toBeInstanceOf(ReflectionNamedType::class);

    return $type->getName();
}

function typedInternalParameterAllowsNull(string $class, string $method, string $parameter): bool
{
    $type = typedInternalParameterReflection($class, $method, $parameter);

    expect($type)->toBeInstanceOf(ReflectionNamedType::class);

    return $type->allowsNull();
}

function typedInternalParameterReflection(string $class, string $method, string $parameter): ?ReflectionType
{
    $reflection = new ReflectionMethod($class, $method);
    $parameters = $reflection->getParameters();
    $names = array_map(
        fn (ReflectionParameter $item) => $item->getName(),
        $parameters,
    );
    $index = array_search($parameter, $names, true);

    expect($index)->not()->toBeFalse();

    return $parameters[$index]->getType();
}
