<?php declare(strict_types=1);

use Cognesy\AgentCtrl\OpenAICodex\Domain\ValueObject\CodexThreadId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeCallId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeMessageId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodePartId;
use Cognesy\AgentCtrl\OpenCode\Domain\ValueObject\OpenCodeSessionId;
use Cognesy\AgentCtrl\ValueObject\AgentSessionId;
use Cognesy\AgentCtrl\ValueObject\AgentToolCallId;

it('supports opaque provider session and thread ids', function () {
    $sessionId = OpenCodeSessionId::fromString('session_abc');
    $threadId = CodexThreadId::fromString('thread_xyz');
    $messageId = OpenCodeMessageId::fromString('msg_123');
    $partId = OpenCodePartId::fromString('part_456');
    $callId = OpenCodeCallId::fromString('call_789');
    $agentSessionId = AgentSessionId::fromString('session_global');
    $agentToolCallId = AgentToolCallId::fromString('call_global');

    expect($sessionId->toString())->toBe('session_abc')
        ->and($threadId->toString())->toBe('thread_xyz')
        ->and($messageId->toString())->toBe('msg_123')
        ->and($partId->toString())->toBe('part_456')
        ->and($callId->toString())->toBe('call_789')
        ->and($agentSessionId->toString())->toBe('session_global')
        ->and($agentToolCallId->toString())->toBe('call_global');
});

it('rejects empty provider ids', function () {
    new OpenCodeSessionId('');
})->throws(\InvalidArgumentException::class);
