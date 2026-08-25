<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\Data\AgentState;
use Throwable;

final class AgentResult
{
    /** @return array<string, mixed> */
    public static function fromState(AgentState $state, array $warnings = [], bool $transient = false): array
    {
        $status = $state->status();

        $result = [
            'status' => match ($status) {
                null => 'unknown',
                default => $status->value,
            },
            'answer' => trim($state->finalResponse()->toString()),
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

        return $result;
    }

    private function __construct() {}
}
