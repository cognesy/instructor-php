<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\ReAct\ReActDriver;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Instructor\Creation\StructuredOutputConfigBuilder;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\InferenceRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

it('builds structured cached context from typed messages', function () {
    $events = new EventDispatcher('agents.test.react-driver-structured-cache');
    $inference = InferenceRuntime::fromProvider(
        provider: LLMProvider::new(),
        events: $events,
    );
    $structuredOutput = new StructuredOutputRuntime(
        inference: $inference,
        events: $events,
        config: (new StructuredOutputConfigBuilder())->create(),
    );
    $driver = new ReActDriver(
        inference: $inference,
        structuredOutput: $structuredOutput,
        events: $events,
    );
    $state = AgentState::empty()->withSystemPrompt('You are helpful.');
    $resolver = \Closure::bind(
        static fn(ReActDriver $driver, AgentState $state) => $driver->structuredCachedContext($state),
        null,
        ReActDriver::class,
    );
    $cached = $resolver($driver, $state);

    expect($cached)->not()->toBeNull()
        ->and($cached->messages()->toArray())->toHaveCount(1)
        ->and($cached->messages()->toArray()[0]['role'])->toBe('system')
        ->and($cached->messages()->toArray()[0]['content'])->toBe('You are helpful.');
});
