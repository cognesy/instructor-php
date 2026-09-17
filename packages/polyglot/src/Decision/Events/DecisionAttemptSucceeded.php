<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Events;

use Cognesy\Polyglot\Decision\Data\DecisionUsage;

final class DecisionAttemptSucceeded extends DecisionEvent
{
    /** @param array<string,mixed> $data */
    public function __construct(
        public readonly string $executionId,
        public readonly string $requestId,
        public readonly string $attemptId,
        public readonly int $attemptNumber,
        public readonly float $durationMs,
        DecisionUsage $usage,
        array $data = [],
    ) {
        parent::__construct([
            ...$data,
            'executionId' => $this->executionId,
            'requestId' => $this->requestId,
            'attemptId' => $this->attemptId,
            'attemptNumber' => $this->attemptNumber,
            'durationMs' => $this->durationMs,
            ...self::usage($usage),
        ]);
    }

    private static function usage(DecisionUsage $usage): array
    {
        return [
            'inputTokens' => $usage->inputTokens(),
            'outputTokens' => $usage->outputTokens(),
            'totalTokens' => $usage->totalTokens(),
        ];
    }
}
