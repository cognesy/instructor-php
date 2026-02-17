<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Drivers\ReAct\ReActDriver;
use Cognesy\Agents\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Events\EventBusResolver;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\Contracts\CanAcceptLLMProvider;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use ReflectionClass;
use ReflectionMethod;

describe('Runtime-first driver contracts', function () {
    it('does not expose CanAcceptLLMProvider on runtime-first drivers', function () {
        $events = EventBusResolver::using(null);
        $inference = InferenceRuntime::fromProvider(
            provider: LLMProvider::new(),
            events: $events,
        );
        $structuredOutput = new StructuredOutputRuntime(
            inference: $inference,
            events: $events,
            config: (new StructuredOutputConfigBuilder())->create(),
        );

        $toolCalling = new ToolCallingDriver(
            inference: $inference,
            events: $events,
        );
        $react = new ReActDriver(
            inference: $inference,
            structuredOutput: $structuredOutput,
            events: $events,
        );

        expect($toolCalling)->not->toBeInstanceOf(CanAcceptLLMProvider::class);
        expect($react)->not->toBeInstanceOf(CanAcceptLLMProvider::class);
    });

    it('requires runtime creators in constructors and has no event rebinding mutator', function () {
        $toolCallingCtor = new ReflectionMethod(ToolCallingDriver::class, '__construct');
        $toolCallingInference = $toolCallingCtor->getParameters()[0];
        $toolCallingMethods = array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(ToolCallingDriver::class))->getMethods(),
        );

        $reactCtor = new ReflectionMethod(ReActDriver::class, '__construct');
        $reactInference = $reactCtor->getParameters()[0];
        $reactStructuredOutput = $reactCtor->getParameters()[1];
        $reactMethods = array_map(
            static fn(ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(ReActDriver::class))->getMethods(),
        );

        expect((string) $toolCallingInference->getType())
            ->toBe('Cognesy\\Polyglot\\Inference\\Contracts\\CanCreateInference');
        expect($toolCallingInference->isOptional())->toBeFalse();
        expect($toolCallingMethods)->not->toContain('withEventHandler');

        expect((string) $reactInference->getType())
            ->toBe('Cognesy\\Polyglot\\Inference\\Contracts\\CanCreateInference');
        expect($reactInference->isOptional())->toBeFalse();
        expect((string) $reactStructuredOutput->getType())
            ->toBe('Cognesy\\Instructor\\Contracts\\CanCreateStructuredOutput');
        expect($reactStructuredOutput->isOptional())->toBeFalse();
        expect($reactMethods)->not->toContain('withEventHandler');
    });
});
