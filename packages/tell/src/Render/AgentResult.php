<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\Data\AgentState;
use Throwable;

final class AgentResult
{
    /** @return array<string, mixed> */
    public static function fromState(AgentState $state): array
    {
        $status = $state->status();

        return [
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
        ];
    }

    private function __construct() {}
}
