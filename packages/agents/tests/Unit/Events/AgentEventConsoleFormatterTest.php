<?php

use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\InferenceRequestStarted;
use Cognesy\Agents\Events\Support\AgentEventConsoleFormatter;
use Cognesy\Agents\Events\ToolCallStarted;

it('formats execution started with context and label', function () {
    $formatter = new AgentEventConsoleFormatter();

    $line = $formatter->format(new AgentExecutionStarted(
        agentId: 'agent-1111-2222-3333-4444',
        parentAgentId: 'parent-aaaa-bbbb-cccc-dddd',
        messageCount: 2,
        availableTools: 3,
    ));

    expect($line)->not->toBeNull();
    expect($line?->label)->toBe('EXEC');
    expect($line?->context)->toBe('[dddd:4444]');
    expect($line?->message)->toContain('messages=2');
    expect($line?->message)->toContain('tools=3');
});

it('uses tracked context for tool events that do not carry agent id', function () {
    $formatter = new AgentEventConsoleFormatter();

    $formatter->format(new AgentExecutionStarted(
        agentId: 'agent-1111-2222-3333-4444',
        parentAgentId: null,
        messageCount: 1,
        availableTools: 1,
    ));

    $line = $formatter->format(new ToolCallStarted(
        tool: 'search',
        args: ['q' => 'Paris'],
        startedAt: new DateTimeImmutable(),
    ));

    expect($line)->not->toBeNull();
    expect($line?->label)->toBe('TOOL');
    expect($line?->context)->toBe('[----:4444]');
    expect($line?->message)->toContain('Calling search');
});

it('respects inference visibility toggle', function () {
    $formatter = new AgentEventConsoleFormatter(showInference: false);

    $line = $formatter->format(new InferenceRequestStarted(
        agentId: 'agent-1111-2222-3333-4444',
        parentAgentId: null,
        stepNumber: 1,
        messageCount: 2,
        model: 'gpt-5-mini',
    ));

    expect($line)->toBeNull();
});
