<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data;

use Cognesy\Addons\Core\StepContracts\HasStepMessages;
use Cognesy\Addons\Core\StepContracts\HasStepUsage;
use Cognesy\Addons\ToolUse\Data\Collections\ToolExecutions;
use Cognesy\Addons\ToolUse\Enums\StepType;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCalls;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;
use Throwable;

final readonly class ToolUseStep implements HasStepUsage, HasStepMessages
{
    public string $id;
    public DateTimeImmutable $createdAt;

    private Messages $inputMessages;
    private Messages $outputMessages;
    private Usage $usage;

    private ToolCalls $toolCalls;
    private ToolExecutions $toolExecutions;
    private InferenceResponse $inferenceResponse;
    private StepType $stepType;

    public function __construct(
        ?Messages          $inputMessages = null,
        ?Messages          $outputMessages = null,
        ?Usage             $usage = null,
        ?ToolCalls         $toolCalls = null,
        ?ToolExecutions    $toolExecutions = null,
        ?InferenceResponse $inferenceResponse = null,
        ?StepType          $stepType = null,

        ?string            $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();

        $this->inputMessages = $inputMessages ?? Messages::empty();
        $this->outputMessages = $outputMessages ?? Messages::empty();
        $this->usage = $usage ?? Usage::none();

        $this->toolCalls = $toolCalls ?? new ToolCalls();
        $this->toolExecutions = $toolExecutions ?? new ToolExecutions();
        $this->inferenceResponse = $inferenceResponse ?? new InferenceResponse();
        $this->stepType = $stepType ?? self::inferStepType(
            $this->inferenceResponse,
            $this->toolExecutions,
        );
    }

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function fromArray(array $data) : ToolUseStep {
        return new ToolUseStep(
            inputMessages: isset($data['inputMessages']) ? Messages::fromArray($data['inputMessages']) : Messages::empty(),
            outputMessages: isset($data['outputMessages']) ? Messages::fromArray($data['outputMessages']) : Messages::empty(),
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            toolCalls: isset($data['toolCalls']) ? ToolCalls::fromArray($data['toolCalls']) : null,
            toolExecutions: isset($data['toolExecutions']) ? ToolExecutions::fromArray($data['toolExecutions']) : null,
            inferenceResponse: isset($data['inferenceResponse']) ? InferenceResponse::fromArray($data['inferenceResponse']) : null,
            stepType: isset($data['stepType']) ? StepType::from($data['stepType']) : null,
            id: $data['id'] ?? null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
        );
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function outputMessages() : Messages {
        return $this->outputMessages;
    }

    public function inputMessages() : Messages {
        return $this->inputMessages ?? Messages::empty();
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
            default => new ToolExecutions(...$this->toolExecutions->havingErrors()),
        };
    }

    public function toArray() : array {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'inputMessages' => $this->inputMessages->toArray(),
            'outputMessages' => $this->outputMessages->toArray(),
            'toolCalls' => $this->toolCalls?->toArray() ?? [],
            'toolExecutions' => $this->toolExecutions?->toArray() ?? [],
            'usage' => $this->usage?->toArray() ?? [],
            'inferenceResponse' => $this->inferenceResponse?->toArray() ?? null,
            'stepType' => $this->stepType->value,
        ];
    }

    public function toString() : string {
        return ($this->outputMessages->toString() ?: '(no response)')
            . ' ['
            . ($this->hasToolCalls() ? $this->toolCalls->toString() : '(-)')
            . ']';
    }

    private static function inferStepType(InferenceResponse $response, ToolExecutions $executions) : StepType {
        return match(true) {
            $executions->hasErrors() => StepType::Error,
            $response->hasToolCalls() => StepType::ToolExecution,
            default => StepType::FinalResponse,
        };
    }
}
