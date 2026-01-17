<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Core\Data\AgentState;
use Cognesy\Addons\Agent\Core\Data\AgentStep;
use Cognesy\Addons\Agent\Serialization\SlimAgentStateSerializer;
use Cognesy\Addons\Agent\Serialization\SlimSerializationConfig;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Data\Usage;

function makeSerializedState(): AgentState {
    $messages = Messages::fromArray([
        ['role' => 'user', 'content' => 'short'],
        [
            'role' => 'assistant',
            'content' => '0123456789',
            '_metadata' => [
                'tool_calls' => [[
                    'id' => 'call_1',
                    'function' => [
                        'name' => 'search',
                        'arguments' => '{"q":"hello"}',
                    ],
                ]],
            ],
        ],
        ['role' => 'tool', 'content' => 'tool output'],
    ]);

    $state = AgentState::empty()
        ->with(agentId: 'agent-1', parentAgentId: 'agent-0')
        ->withMessages($messages);

    $state = $state->withStateInfo(
        $state->stateInfo()->addExecutionTime(12.5)
    );

    $step1 = new AgentStep(
        toolCalls: new ToolCalls(new ToolCall('lookup', ['q' => 'x'], 'call_1')),
        usage: new Usage(1, 2),
    );
    $step2 = new AgentStep(
        toolCalls: new ToolCalls(),
        usage: new Usage(3, 4),
    );

    return $state->withAddedSteps($step1, $step2);
}

it('serializes messages with truncation', function () {
    $state = makeSerializedState();
    $config = new SlimSerializationConfig(
        maxMessages: 2,
        maxSteps: 0,
        maxContentLength: 4,
        includeToolResults: true,
        includeSteps: false,
        includeContinuationTrace: false,
        redactToolArgs: false,
    );
    $serializer = new SlimAgentStateSerializer($config);

    $data = $serializer->serialize($state);

    expect(count($data['messages']))->toBe(2);
    expect($data['messages'][0]['content'])->toBe('0123...');
    expect($data['messages'][1]['content'])->toBe('tool...');
});

it('redacts tool args and omits tool results', function () {
    $state = makeSerializedState();
    $config = new SlimSerializationConfig(
        maxMessages: 10,
        maxSteps: 0,
        maxContentLength: 2000,
        includeToolResults: false,
        includeSteps: false,
        includeContinuationTrace: false,
        redactToolArgs: true,
    );
    $serializer = new SlimAgentStateSerializer($config);

    $data = $serializer->serialize($state);

    $assistant = $data['messages'][1];
    $toolCalls = $assistant['_metadata']['tool_calls'] ?? [];

    expect($toolCalls[0])->toMatchArray(['id' => 'call_1', 'name' => 'search']);
    expect($toolCalls[0])->not->toHaveKey('function');
    expect($data['messages'][2]['content'])->toBe('[tool result omitted]');
});

it('serializes steps and execution timing', function () {
    $state = makeSerializedState();
    $config = new SlimSerializationConfig(
        maxMessages: 10,
        maxSteps: 1,
        maxContentLength: 2000,
        includeToolResults: true,
        includeSteps: true,
        includeContinuationTrace: false,
        redactToolArgs: false,
    );
    $serializer = new SlimAgentStateSerializer($config);

    $data = $serializer->serialize($state);

    expect($data['execution']['cumulative_seconds'])->toBe(12.5);
    expect(count($data['steps']))->toBe(1);
    expect($data['steps'][0]['step_number'])->toBe(1);
});

it('deserializes minimal agent state', function () {
    $state = makeSerializedState();
    $serializer = new SlimAgentStateSerializer();

    $data = $serializer->serialize($state);
    $restored = $serializer->deserialize($data);

    expect($restored->agentId)->toBe('agent-1');
    expect($restored->parentAgentId)->toBe('agent-0');
    expect(count($restored->messages()))->toBe(3);
});
