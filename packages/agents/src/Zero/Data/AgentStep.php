<?php declare(strict_types=1);

namespace Cognesy\Agents\Zero\Data;

use Cognesy\Agents\Core\Collections\ErrorList;
use Cognesy\Agents\Core\Collections\ToolExecutions;
use Cognesy\Agents\Core\Enums\AgentStepType;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Utils\Uuid;
use Throwable;

/**
 * Immutable step snapshot from agent execution.
 *
 * Timing and continuation outcome are owned by StepExecution.
 */
final readonly class AgentStep
{
    private string $id;
    private Messages $inputMessages;
    private InferenceResponse $inferenceResponse;
    private ToolExecutions $toolExecutions;
    private ErrorList $errors;
    private Messages $outputMessages;

    public function __construct(
        ?Messages $inputMessages = null,
        ?Messages $outputMessages = null,
        ?InferenceResponse $inferenceResponse = null,
        ?ToolExecutions $toolExecutions = null,
        ?ErrorList $errors = null,
        ?string $id = null,
    ) {
        $providedId = $id ?? '';
        $this->id = $providedId !== '' ? $providedId : Uuid::uuid4();
        $this->inputMessages = $inputMessages ?? Messages::empty();
        $this->outputMessages = $outputMessages ?? Messages::empty();
        $this->toolExecutions = $toolExecutions ?? new ToolExecutions();
        $this->inferenceResponse = $inferenceResponse ?? new InferenceResponse();

        $providedErrors = $errors ?? ErrorList::empty();
        $toolErrors = $this->toolExecutions->errors();
        $this->errors = $toolErrors->withAppended(...$providedErrors->all());
    }

    // ERRORS //////////////////////////////////////////////////////

    public function hasErrors(): bool {
        return $this->errors->hasAny();
    }

    public function errors(): ErrorList {
        return $this->errors;
    }

    public function errorsAsString(): string {
        return $this->errors->toMessagesString();
    }

    public function id(): string {
        return $this->id;
    }

    // MESSAGES ////////////////////////////////////////////////////

    public function inputMessages(): Messages {
        return $this->inputMessages;
    }

    public function outputMessages(): Messages {
        return $this->outputMessages;
    }

    // INFERENCE RESPONSE //////////////////////////////////////////

    public function inferenceResponse(): InferenceResponse {
        return $this->inferenceResponse;
    }

    public function usage(): Usage {
        return $this->inferenceResponse->usage();
    }

    public function finishReason(): ?InferenceFinishReason {
        return $this->inferenceResponse->finishReason();
    }

    // TOOL CALLS //////////////////////////////////////////////////

    /**
     * Tool calls requested by the model in this step.
     */
    public function requestedToolCalls(): ToolCalls {
        return $this->inferenceResponse->toolCalls();
    }

    /**
     * Tool calls that were actually executed in this step.
     */
    public function executedToolCalls(): ToolCalls {
        return $this->toolExecutions->toolCalls();
    }

    /**
     * Legacy alias for requested tool calls.
     */
    public function toolCalls(): ToolCalls {
        return $this->requestedToolCalls();
    }

    public function hasToolCalls(): bool {
        return $this->requestedToolCalls()->hasAny();
    }

    public function stepType(): AgentStepType {
        return $this->deriveStepType();
    }

    // TOOL EXECUTIONS /////////////////////////////////////////////

    public function toolExecutions(): ToolExecutions {
        return $this->toolExecutions;
    }

    public function errorExecutions(): ToolExecutions {
        return new ToolExecutions(...$this->toolExecutions->havingErrors());
    }

    public static function failure(Messages $inputMessages, Throwable $error): self {
        return new self(
            inputMessages: $inputMessages,
            outputMessages: Messages::empty(),
            errors: new ErrorList($error),
        );
    }

    // SERIALIZATION ///////////////////////////////////////////////

    public function toArray(): array {
        return [
            'id' => $this->id,
            'inputMessages' => $this->inputMessages->toArray(),
            'outputMessages' => $this->outputMessages->toArray(),
            'toolExecutions' => $this->toolExecutions->toArray(),
            'errors' => array_map(
                static fn(Throwable $error): array => [
                    'message' => $error->getMessage(),
                    'class' => get_class($error),
                ],
                $this->errors->all(),
            ),
            'inferenceResponse' => $this->inferenceResponse->toArray(),
        ];
    }

    public static function fromArray(array $data): self {
        $inferenceResponse = self::hydrateInferenceResponse($data);

        return new self(
            inputMessages: isset($data['inputMessages'])
                ? Messages::fromArray($data['inputMessages'])
                : Messages::empty(),
            outputMessages: isset($data['outputMessages'])
                ? Messages::fromArray($data['outputMessages'])
                : Messages::empty(),
            inferenceResponse: $inferenceResponse,
            toolExecutions: isset($data['toolExecutions'])
                ? ToolExecutions::fromArray($data['toolExecutions'])
                : null,
            errors: ErrorList::fromArray($data['errors'] ?? []),
            id: $data['id'] ?? null,
        );
    }

    public function toString(): string {
        $toolCalls = $this->requestedToolCalls();

        return ($this->outputMessages()->toString() ?: '(no response)')
            . ' ['
            . ($toolCalls->hasAny() ? $toolCalls->toString() : '(-)')
            . ']';
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function deriveStepType(): AgentStepType {
        if ($this->errors->hasAny()) {
            return AgentStepType::Error;
        }

        if ($this->toolExecutions->hasErrors()) {
            return AgentStepType::Error;
        }

        if ($this->requestedToolCalls()->hasAny()) {
            return AgentStepType::ToolExecution;
        }

        return AgentStepType::FinalResponse;
    }

    private static function hydrateInferenceResponse(array $data): InferenceResponse {
        $response = isset($data['inferenceResponse'])
            ? InferenceResponse::fromArray($data['inferenceResponse'])
            : new InferenceResponse();

        if (isset($data['toolCalls']) && $response->toolCalls()->hasNone()) {
            $response = $response->with(toolCalls: ToolCalls::fromArray($data['toolCalls']));
        }

        if (isset($data['usage']) && $response->usage()->total() === 0) {
            $response = $response->with(usage: Usage::fromArray($data['usage']));
        }

        return $response;
    }
}
