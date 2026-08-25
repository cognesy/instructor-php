<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\Data\AgentState;
use Throwable;

final class AgentResult
{
    /** @return array<string, mixed> */
    /** @param array{name: string, source: 'current'|'invocation'}|null $branch */
    public static function fromState(AgentState $state, array $warnings = [], bool $transient = false, ?array $branch = null): array
    {
        $status = $state->status();

        $result = [
            'status' => match ($status) {
                null => 'unknown',
                default => $status->value,
            },
            'answer' => self::answer($state),
            'steps' => $state->stepCount(),
            'usage' => $state->usage()->toArray(),
            'errors' => array_map(
                static fn (Throwable $error): string => $error->getMessage(),
                $state->errors()->all(),
            ),
            'execution' => [
                'mode' => $transient ? 'transient' : 'durable',
                'durable' => ! $transient,
            ],
        ];
        if ($warnings !== []) {
            $result['warnings'] = $warnings;
        }
        if ($branch !== null) {
            $result['branch'] = $branch;
        }
        $signal = $state->stopSignal();
        if ($signal !== null) {
            $result['termination'] = [
                'reason' => $signal->reason->value,
                'message' => $signal->message,
            ];
        }

        return $result;
    }

    public static function answer(AgentState $state): string
    {
        return $state->stopSignal()?->reason->value === 'output_limit'
            ? ''
            : trim($state->finalResponse()->toString());
    }

    private function __construct() {}
}
