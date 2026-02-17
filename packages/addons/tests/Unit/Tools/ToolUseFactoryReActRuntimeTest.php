<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Tools;

use Cognesy\Addons\ToolUse\Drivers\ReAct\ReActDriver;
use Cognesy\Addons\ToolUse\Drivers\ToolCalling\ToolCallingDriver;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Events\EventBusResolver;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;
use ReflectionMethod;
use ReflectionProperty;

it('injects constructor-provided runtime creators into ReAct driver', function () {
    $events = EventBusResolver::using(null);
    $inferenceRuntime = InferenceRuntime::fromProvider(
        provider: LLMProvider::new(),
        events: $events,
    );
    $structuredOutputRuntime = new StructuredOutputRuntime(
        inference: $inferenceRuntime,
        events: $events,
        config: (new StructuredOutputConfigBuilder())->create(),
    );

    $toolUse = ToolUseFactory::react(
        inference: $inferenceRuntime,
        structuredOutput: $structuredOutputRuntime,
        events: $events,
    );

    $driver = $toolUse->driver();
    expect($driver)->toBeInstanceOf(ReActDriver::class);

    $inferenceProperty = new ReflectionProperty($driver, 'inference');
    $driverInference = $inferenceProperty->getValue($driver);
    expect($driverInference)->toBe($inferenceRuntime);

    $structuredProperty = new ReflectionProperty($driver, 'structuredOutput');
    $driverStructuredOutput = $structuredProperty->getValue($driver);
    expect($driverStructuredOutput)->toBe($structuredOutputRuntime);
});

it('requires runtime creators in tool-use driver constructors', function () {
    $toolCallingCtor = new ReflectionMethod(ToolCallingDriver::class, '__construct');
    $toolCallingInference = $toolCallingCtor->getParameters()[0];

    $reactCtor = new ReflectionMethod(ReActDriver::class, '__construct');
    $reactInference = $reactCtor->getParameters()[0];
    $reactStructuredOutput = $reactCtor->getParameters()[1];

    expect((string) $toolCallingInference->getType())
        ->toBe('Cognesy\\Polyglot\\Inference\\Contracts\\CanCreateInference');
    expect($toolCallingInference->isOptional())->toBeFalse();

    expect((string) $reactInference->getType())
        ->toBe('Cognesy\\Polyglot\\Inference\\Contracts\\CanCreateInference');
    expect($reactInference->isOptional())->toBeFalse();
    expect((string) $reactStructuredOutput->getType())
        ->toBe('Cognesy\\Instructor\\Contracts\\CanCreateStructuredOutput');
    expect($reactStructuredOutput->isOptional())->toBeFalse();
});
