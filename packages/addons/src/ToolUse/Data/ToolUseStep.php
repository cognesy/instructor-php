<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

use Cognesy\Addons\ToolUse\Data\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Enums\StepType;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Throwable;

final readonly class ToolUseStep
{
    private string $response;
    private ToolCalls $toolCalls;
    private ToolExecutions $toolExecutions;
    private Messages $messages;
    private Usage $usage;
    private InferenceResponse $inferenceResponse;
    private StepType $stepType;

    public function __construct(
        string             $response = '',
        ?ToolCalls         $toolCalls = null,
        ?ToolExecutions    $toolExecutions = null,
        ?Messages          $messages = null,
        ?Usage             $usage = null,
        ?InferenceResponse $inferenceResponse = null,
        ?StepType          $stepType = null,
    ) {
        $this->response = $response ?? '';
        $this->toolCalls = $toolCalls ?? new ToolCalls();
        $this->toolExecutions = $toolExecutions ?? new ToolExecutions();
        $this->messages = $messages ?? Messages::empty();
        $this->usage = $usage ?? Usage::none();
        $this->inferenceResponse = $inferenceResponse ?? new InferenceResponse();
        $this->stepType = $stepType ?? self::inferStepType(
            $this->inferenceResponse,
            $this->toolExecutions,
        );
    }

    public function response() : string {
        return $this->response ?? '';
    }

    public function messages() : Messages {
        return $this->messages ?? Messages::empty();
    }

    public function toolExecutions() : ToolExecutions {
        return $this->toolExecutions ?? new ToolExecutions();
    }

    public function usage() : Usage {
        return $this->usage ?? new Usage();
    }

    public function finishReason() : ?InferenceFinishReason {
        return $this->inferenceResponse?->finishReason();
    }

    public function inferenceResponse() : ?InferenceResponse {
        return $this->inferenceResponse;
    }

    public function stepType() : StepType {
        return $this->stepType;
    }

    // HANDLE TOOL CALLS ////////////////////////////////////////////

    public function toolCalls() : ToolCalls {
        return $this->toolCalls ?? new ToolCalls();
    }

    public function hasToolCalls() : bool {
        return $this->toolCalls()->count() > 0;
    }

    // HANDLE ERRORS ////////////////////////////////////////////////

    public function hasErrors() : bool {
        return match($this->toolExecutions) {
            null => false,
            default => $this->toolExecutions->hasErrors(),
        };
    }

    /**
     * @return Throwable[]
     */
    public function errors() : array {
        return $this->toolExecutions?->errors() ?? [];
    }

    public function errorsAsString() : string {
        return implode("\n", array_map(
            callback: fn(Throwable $e) => $e->getMessage(),
            array: $this->errors(),
        ));
    }

    public function errorExecutions() : ToolExecutions {
        return match($this->toolExecutions) {
            null => new ToolExecutions(),
            default => new ToolExecutions($this->toolExecutions->withErrors()),
        };
    }

    public function toArray() : array {
        return [
            'response' => $this->response,
            'toolCalls' => $this->toolCalls?->toArray() ?? [],
            'toolExecutions' => $this->toolExecutions?->toArray() ?? [],
            'messages' => $this->messages?->toArray() ?? [],
            'usage' => $this->usage?->toArray() ?? [],
            'inferenceResponse' => $this->inferenceResponse?->toArray() ?? null,
            'stepType' => $this->stepType->value,
        ];
    }

    public function toString() : string {
        $toolNames = $this->hasToolCalls() 
            ? implode(', ', $this->toolCalls()->map(fn($call) => $call->name()))
            : '-';
        
        return implode(' | ', [
            'response:',
            $this->response ?: '-',
            'tools called:',
            $toolNames,
        ]);
    }

    private static function inferStepType(InferenceResponse $response, ToolExecutions $executions) : StepType {
        return match(true) {
            $executions->hasErrors() => StepType::Error,
            $response->hasToolCalls() => StepType::ToolExecution,
            default => StepType::FinalResponse,
        };
    }
}
