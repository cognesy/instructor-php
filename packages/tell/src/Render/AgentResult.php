<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\Data\AgentState;
use Cognesy\Tell\Data\TellExecutionMode;
use Throwable;

final class AgentResult
{
    /** @return array<string, mixed> */
    /** @param array{name: string, source: 'current'|'invocation'}|null $branch */
    /** @param list<array{code: string, source: string, severity: string, message: string}> $diagnostics */
    public static function fromState(AgentState $state, array $warnings = [], TellExecutionMode $mode = TellExecutionMode::Stateless, ?array $branch = null, array $diagnostics = []): array {
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
                // Automatic is a request-side mode that always resolves to one
                // of the three outcomes before a result exists; it never
                // describes what a finished turn persisted.
                'mode' => $mode === TellExecutionMode::Automatic
                    ? TellExecutionMode::Stateless->value
                    : $mode->value,
                'durable' => $mode === TellExecutionMode::Durable,
            ],
        ];
        if ($warnings !== []) {
            $result['warnings'] = $warnings;
        }
        if ($branch !== null) {
            $result['branch'] = $branch;
        }
        if ($diagnostics !== []) {
            $result['diagnostics'] = $diagnostics;
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

    public static function answer(AgentState $state): string {
        return $state->stopSignal()?->reason->value === 'output_limit'
            ? ''
            : trim($state->finalResponse()->toString());
    }

    private function __construct() {}
}
