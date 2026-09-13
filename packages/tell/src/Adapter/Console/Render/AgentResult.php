<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Render;

use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Tell\Data\TellDiagnostic;
use Cognesy\Tell\Data\TellExecutionError;
use Cognesy\Tell\Data\TellResult;

final class AgentResult
{
    /** @return array<string, mixed> */
    public static function fromResult(TellResult $tell): array {
        $termination = $tell->termination();
        $publication = $tell->publication();
        $result = [
            'execution' => [
                'id' => $termination->executionId,
                'status' => $termination->status->value,
                'requestedMode' => $tell->requestedMode()->value,
                'mode' => $tell->mode()->value,
                'steps' => $termination->stepCount,
                'usage' => $termination->usage->toArray(),
            ],
            'termination' => [
                'reason' => $termination->stopSignal?->reason->value,
                'source' => $termination->stopSignal?->source,
                'context' => $termination->toArray()['context'],
                'lastStepType' => $termination->lastStepType?->value,
                'finishReason' => $termination->finishReason?->value,
            ],
            'publication' => [
                ...$publication->toArray(),
                'headChanged' => $publication->isPublished(),
            ],
            'trace' => $tell->trace()->toArray(),
            'answer' => self::answer($tell),
            'errors' => $termination->errors->toArray(),
        ];
        if ($tell->warnings() !== []) {
            $result['warnings'] = $tell->warnings();
        }
        if ($tell->diagnostics() !== []) {
            $result['diagnostics'] = array_map(
                static fn (TellDiagnostic $diagnostic): array => $diagnostic->toArray(),
                $tell->diagnostics(),
            );
        }

        return $result;
    }

    public static function answer(TellResult $result): string {
        return $result->termination()->stopSignal?->reason === StopReason::OutputLimitReached
            ? ''
            : trim($result->text());
    }

    /** @return list<string> */
    public static function errorMessages(TellResult $result): array {
        return array_map(
            static fn (TellExecutionError $error): string => $error->message,
            $result->termination()->errors->all(),
        );
    }

    public static function terminalSummary(TellResult $result): ?string {
        $termination = $result->termination();
        if ($termination->status === ExecutionStatus::Completed) {
            return null;
        }
        $reason = $termination->stopSignal?->reason->value ?? 'none';

        return sprintf(
            '[tell] execution %s: id=%s reason=%s publication=%s',
            $termination->status->value,
            $termination->executionId,
            $reason,
            $result->publication()->status->value,
        );
    }

    private function __construct() {}
}
