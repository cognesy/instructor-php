<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Core\Data\AgentExecution;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;

describe('AgentExecution error message', function () {
    it('returns an error message when execution fails', function () {
        $toolCall = new ToolCall('structured_output', ['input' => 'x', 'schema' => 'lead']);
        $execution = new AgentExecution(
            toolCall: $toolCall,
            result: Result::failure(new \RuntimeException('boom')),
            startedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            endedAt: new DateTimeImmutable('2024-01-01T00:00:01Z'),
        );

        expect($execution->errorMessage())->toBe('boom');
    });

    it('returns empty string when execution succeeds', function () {
        $toolCall = new ToolCall('structured_output', ['input' => 'x', 'schema' => 'lead']);
        $execution = new AgentExecution(
            toolCall: $toolCall,
            result: Result::success('ok'),
            startedAt: new DateTimeImmutable('2024-01-01T00:00:00Z'),
            endedAt: new DateTimeImmutable('2024-01-01T00:00:01Z'),
        );

        expect($execution->errorMessage())->toBe('');
    });
});
