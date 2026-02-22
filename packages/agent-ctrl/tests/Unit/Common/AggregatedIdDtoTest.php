<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Dto\ToolCall;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\ValueObject\AgentSessionId;
use Cognesy\AgentCtrl\ValueObject\AgentToolCallId;

it('hydrates aggregated tool call id as opaque value object', function () {
    $toolCall = new ToolCall(
        tool: 'bash',
        input: ['command' => 'ls'],
        output: 'ok',
        callId: 'call_123',
    );

    expect((string) ($toolCall->callId() ?? ''))->toBe('call_123')
        ->and($toolCall->callId())->toBeInstanceOf(AgentToolCallId::class)
        ->and((string) ($toolCall->callId() ?? ''))->toBe('call_123');
});

it('hydrates aggregated response session id as opaque value object', function () {
    $response = new AgentResponse(
        agentType: AgentType::OpenCode,
        text: 'done',
        exitCode: 0,
        sessionId: 'session_abc',
    );

    expect((string) ($response->sessionId() ?? ''))->toBe('session_abc')
        ->and($response->sessionId())->toBeInstanceOf(AgentSessionId::class)
        ->and((string) ($response->sessionId() ?? ''))->toBe('session_abc');
});
