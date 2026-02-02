<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Unit\Agent;

use Cognesy\Agents\Core\Context\AgentContext;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Enums\AgentStatus;
use Cognesy\Messages\MessageStore\MessageStore;
use Cognesy\Messages\MessageStore\Section;
use tmp\Serialization\ContinuationAgentStateSerializer;
use tmp\Serialization\ContinuationSerializationConfig;

it('serializes continuation state with limits and metadata', function () {
    $store = MessageStore::fromSections(
        new Section('system'),
        new Section('messages'),
    );
    $store = $store->section('system')->appendMessages([
        ['role' => 'system', 'content' => 'System'],
    ]);
    $store = $store->section('messages')->appendMessages([
        ['role' => 'user', 'content' => 'Hi'],
        [
            'role' => 'assistant',
            'content' => 'Thinking',
            '_metadata' => [
                'tool_calls' => [[
                    'id' => 'call_1',
                    'function' => [
                        'name' => 'search',
                        'arguments' => '{"q":"test"}',
                    ],
                ]],
            ],
        ],
        [
            'role' => 'tool',
            'content' => 'result payload',
            '_metadata' => [
                'tool_call_id' => 'call_1',
            ],
        ],
    ]);

    $state = AgentState::empty()
        ->with(context: new AgentContext(store: $store))
        ->withMetadata('locale', 'en');

    $config = new ContinuationSerializationConfig(
        maxMessagesPerSection: 2,
        maxContentLength: 4,
        includeToolResults: false,
        redactToolArgs: true,
    );
    $serializer = new ContinuationAgentStateSerializer($config);

    $data = $serializer->serialize($state);

    expect($data['context']['metadata'])->toBe(['locale' => 'en']);
    expect($data['context']['messageStore']['sections'])->toHaveCount(2);

    $messages = $data['context']['messageStore']['sections'][1]['messages'];
    expect($messages)->toHaveCount(2);
    expect($messages[0]['content'])->toBe('Thin...');
    expect($messages[0]['_metadata']['tool_calls'][0])->toMatchArray([
        'id' => 'call_1',
        'name' => 'search',
    ]);
    expect($messages[1]['content'])->toBe('[tool result omitted]');
    expect($messages[1]['_metadata']['tool_call_id'])->toBe('call_1');

    $restored = $serializer->deserialize($data);

    expect($restored->status())->toBe(AgentStatus::InProgress)
        ->and($restored->metadata()->toArray())->toBe(['locale' => 'en']);
    expect($restored->store()->sections()->names())->toBe(['system', 'messages']);
    expect($restored->store()->section('messages')->messages()->count())->toBe(2);
    expect($restored->store()->section('messages')->messages()->last()->metadata()->toArray())
        ->toBe(['tool_call_id' => 'call_1']);
})->skip('hooks not integrated yet');
