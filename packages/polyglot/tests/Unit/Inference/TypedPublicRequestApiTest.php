<?php

declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\CachedInferenceContext;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Data\ToolDefinitions;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Traits\HandlesRequestBuilder;

it('keeps inference request mutators typed', function () {
    expect(typedParameterName(InferenceRequest::class, 'withMessages', 'messages'))->toBe(Messages::class)
        ->and(typedParameterName(InferenceRequest::class, '__construct', 'messages'))->toBe(Messages::class)
        ->and(typedParameterAllowsNull(InferenceRequest::class, '__construct', 'messages'))->toBeTrue()
        ->and(typedParameterName(InferenceRequest::class, '__construct', 'tools'))->toBe(ToolDefinitions::class)
        ->and(typedParameterAllowsNull(InferenceRequest::class, '__construct', 'tools'))->toBeTrue()
        ->and(typedParameterName(InferenceRequest::class, '__construct', 'toolChoice'))->toBe(ToolChoice::class)
        ->and(typedParameterAllowsNull(InferenceRequest::class, '__construct', 'toolChoice'))->toBeTrue()
        ->and(typedParameterName(InferenceRequest::class, '__construct', 'responseFormat'))->toBe(ResponseFormat::class)
        ->and(typedParameterAllowsNull(InferenceRequest::class, '__construct', 'responseFormat'))->toBeTrue()
        ->and(typedParameterName(InferenceRequest::class, 'withTools', 'tools'))->toBe(ToolDefinitions::class)
        ->and(typedParameterName(InferenceRequest::class, 'withToolChoice', 'toolChoice'))->toBe(ToolChoice::class)
        ->and(typedParameterName(InferenceRequest::class, 'withResponseFormat', 'responseFormat'))->toBe(ResponseFormat::class)
        ->and(typedParameterName(InferenceRequest::class, 'with', 'messages'))->toBe(Messages::class)
        ->and(typedParameterAllowsNull(InferenceRequest::class, 'with', 'messages'))->toBeTrue()
        ->and(typedParameterName(InferenceRequest::class, 'with', 'tools'))->toBe(ToolDefinitions::class)
        ->and(typedParameterAllowsNull(InferenceRequest::class, 'with', 'tools'))->toBeTrue()
        ->and(typedParameterName(InferenceRequest::class, 'with', 'toolChoice'))->toBe(ToolChoice::class)
        ->and(typedParameterAllowsNull(InferenceRequest::class, 'with', 'toolChoice'))->toBeTrue()
        ->and(typedParameterName(InferenceRequest::class, 'with', 'responseFormat'))->toBe(ResponseFormat::class)
        ->and(typedParameterAllowsNull(InferenceRequest::class, 'with', 'responseFormat'))->toBeTrue();
});

it('keeps inference request builder mutators typed', function () {
    expect(typedParameterName(InferenceRequestBuilder::class, 'withMessages', 'messages'))->toBe(Messages::class)
        ->and(typedParameterName(InferenceRequestBuilder::class, 'withTools', 'tools'))->toBe(ToolDefinitions::class)
        ->and(typedParameterName(InferenceRequestBuilder::class, 'withToolChoice', 'toolChoice'))->toBe(ToolChoice::class)
        ->and(typedParameterName(InferenceRequestBuilder::class, 'withResponseFormat', 'responseFormat'))->toBe(ResponseFormat::class)
        ->and(typedParameterName(CachedInferenceContext::class, '__construct', 'messages'))->toBe(Messages::class)
        ->and(typedParameterAllowsNull(CachedInferenceContext::class, '__construct', 'messages'))->toBeTrue()
        ->and(typedParameterName(CachedInferenceContext::class, '__construct', 'tools'))->toBe(ToolDefinitions::class)
        ->and(typedParameterAllowsNull(CachedInferenceContext::class, '__construct', 'tools'))->toBeTrue()
        ->and(typedParameterName(CachedInferenceContext::class, '__construct', 'toolChoice'))->toBe(ToolChoice::class)
        ->and(typedParameterAllowsNull(CachedInferenceContext::class, '__construct', 'toolChoice'))->toBeTrue()
        ->and(typedParameterName(CachedInferenceContext::class, '__construct', 'responseFormat'))->toBe(ResponseFormat::class)
        ->and(typedParameterAllowsNull(CachedInferenceContext::class, '__construct', 'responseFormat'))->toBeTrue()
        ->and(typedParameterName(InferenceRequestBuilder::class, 'withCachedContext', 'messages'))->toBe(Messages::class)
        ->and(typedParameterAllowsNull(InferenceRequestBuilder::class, 'withCachedContext', 'messages'))->toBeTrue()
        ->and(typedParameterName(InferenceRequestBuilder::class, 'withCachedContext', 'tools'))->toBe(ToolDefinitions::class)
        ->and(typedParameterAllowsNull(InferenceRequestBuilder::class, 'withCachedContext', 'tools'))->toBeTrue()
        ->and(typedParameterName(InferenceRequestBuilder::class, 'withCachedContext', 'toolChoice'))->toBe(ToolChoice::class)
        ->and(typedParameterAllowsNull(InferenceRequestBuilder::class, 'withCachedContext', 'toolChoice'))->toBeTrue()
        ->and(typedParameterName(InferenceRequestBuilder::class, 'withCachedContext', 'responseFormat'))->toBe(ResponseFormat::class)
        ->and(typedParameterAllowsNull(InferenceRequestBuilder::class, 'withCachedContext', 'responseFormat'))->toBeTrue();
});

it('keeps fluent polyglot entry points typed', function () {
    $handler = new class {
        use HandlesRequestBuilder;

        public function __construct() {
            $this->requestBuilder = new InferenceRequestBuilder();
        }
    };

    expect(typedParameterName($handler::class, 'withMessages', 'messages'))->toBe(Messages::class)
        ->and(typedParameterName($handler::class, 'withTools', 'tools'))->toBe(ToolDefinitions::class)
        ->and(typedParameterName($handler::class, 'withToolChoice', 'toolChoice'))->toBe(ToolChoice::class)
        ->and(typedParameterName($handler::class, 'withResponseFormat', 'responseFormat'))->toBe(ResponseFormat::class)
        ->and(typedParameterName(Inference::class, 'with', 'messages'))->toBe(Messages::class)
        ->and(typedParameterAllowsNull(Inference::class, 'with', 'messages'))->toBeTrue()
        ->and(typedParameterName(Inference::class, 'with', 'tools'))->toBe(ToolDefinitions::class)
        ->and(typedParameterAllowsNull(Inference::class, 'with', 'tools'))->toBeTrue()
        ->and(typedParameterName(Inference::class, 'with', 'toolChoice'))->toBe(ToolChoice::class)
        ->and(typedParameterAllowsNull(Inference::class, 'with', 'toolChoice'))->toBeTrue()
        ->and(typedParameterName(Inference::class, 'with', 'responseFormat'))->toBe(ResponseFormat::class)
        ->and(typedParameterAllowsNull(Inference::class, 'with', 'responseFormat'))->toBeTrue();
});

function typedParameterName(string $class, string $method, string $parameter): string
{
    $type = typedParameterReflection($class, $method, $parameter);

    expect($type)->toBeInstanceOf(ReflectionNamedType::class);

    return $type->getName();
}

function typedParameterAllowsNull(string $class, string $method, string $parameter): bool
{
    $type = typedParameterReflection($class, $method, $parameter);

    expect($type)->toBeInstanceOf(ReflectionNamedType::class);

    return $type->allowsNull();
}

function typedParameterReflection(string $class, string $method, string $parameter): ?ReflectionType
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
