<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Agents\Collections\ToolExecutions;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentStepId;
use Cognesy\Agents\Data\StepExecution;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Agents\Data\ToolExecutionId;
use Cognesy\Agents\Enums\AgentStepType;
use Cognesy\Agents\Exceptions\ToolExecutionException;
use Cognesy\Messages\Messages;
use Cognesy\Messages\ToolCall;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Utils\Exceptions\ErrorList;
use Cognesy\Utils\Result\Result;
use DateTimeImmutable;
use Throwable;

/**
 * Immutable safe projection of a single `StepExecution` for eval trajectories.
 *
 * Deliberately carries no input messages and no raw `InferenceResponse` - see
 * `toArray()`. The conversation is written once per turn by the session, not once
 * per step, because `AgentStep::inputMessages()` holds the whole prior conversation
 * and repeating it per step is quadratic in step count.
 */
final readonly class EvalStep
{
    public function __construct(
        private AgentStepId $id,
        private int $turn,
        private int $index,
        private AgentStepType $type,
        private Messages $outputMessages,
        private ToolCalls $requestedToolCalls,
        private ToolExecutions $toolExecutions,
        private ?InferenceFinishReason $finishReason,
        private InferenceUsage $usage,
        private ?StopSignal $stopSignal,
        private ErrorList $errors,
        private DateTimeImmutable $startedAt,
        private DateTimeImmutable $completedAt,
        private float $duration,
        private EvalTracePolicy $policy,
    ) {}

    // CONSTRUCTORS ////////////////////////////////////////////

    public static function fromStepExecution(
        StepExecution $execution,
        int $turn,
        int $index,
        ?EvalTracePolicy $policy = null,
    ): self {
        $step = $execution->step();
        return new self(
            id: $execution->id(),
            turn: $turn,
            index: $index,
            type: $step->stepType(),
            outputMessages: $step->outputMessages(),
            requestedToolCalls: $step->requestedToolCalls(),
            toolExecutions: $step->toolExecutions(),
            finishReason: $step->finishReason(),
            usage: $step->usage(),
            stopSignal: $execution->continuation()->stopSignal(),
            errors: $step->errors(),
            startedAt: $execution->startedAt(),
            completedAt: $execution->completedAt(),
            duration: $execution->duration(),
            policy: $policy ?? EvalTracePolicy::safe(),
        );
    }

    // ACCESSORS ///////////////////////////////////////////////

    public function id(): AgentStepId {
        return $this->id;
    }

    public function turn(): int {
        return $this->turn;
    }

    public function index(): int {
        return $this->index;
    }

    public function type(): AgentStepType {
        return $this->type;
    }

    public function outputMessages(): Messages {
        return $this->outputMessages;
    }

    public function requestedToolCalls(): ToolCalls {
        return $this->requestedToolCalls;
    }

    public function toolExecutions(): ToolExecutions {
        return $this->toolExecutions;
    }

    public function finishReason(): ?InferenceFinishReason {
        return $this->finishReason;
    }

    public function usage(): InferenceUsage {
        return $this->usage;
    }

    public function duration(): float {
        return $this->duration;
    }

    public function stopSignal(): ?StopSignal {
        return $this->stopSignal;
    }

    public function errors(): ErrorList {
        return $this->errors;
    }

    public function hasErrors(): bool {
        return $this->errors->hasAny();
    }

    // SERIALIZATION ///////////////////////////////////////////

    /**
     * Deliberately safe schema. Never calls `InferenceResponse::toArray()`, so
     * `responseData` and reasoning content cannot leak, and never serializes input
     * messages. Under `EvalTracePolicy::safe()`, each tool call's arguments, each
     * successful tool execution's result, each failed tool execution's error
     * message (`toolExecutions[].error`), AND each step-level error's message
     * (`errors[].message` below - a distinct, framework-level error list from
     * `toolExecutions[].error`, but populated from the same underlying exceptions
     * and just as capable of embedding offending input) serialize as a digest
     * (hash/bytes/preview) rather than verbatim. `errors[].class` stays in the
     * clear - an exception's class name is not payload-derived and is useful for
     * triage. Everything else - names, ids, ordering, the `hasError` boolean,
     * timings, usage, finish reason, stop signal - stays in the clear too, because
     * deterministic trajectory assertions read it.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'id' => $this->id->value,
            'turn' => $this->turn,
            'index' => $this->index,
            'type' => $this->type->value,
            'outputMessages' => $this->outputMessages->toArray(),
            'requestedToolCalls' => array_map(
                fn (ToolCall $call): array => $this->toolCallToArray($call),
                $this->requestedToolCalls->all(),
            ),
            'toolExecutions' => array_map(
                fn (ToolExecution $execution): array => $this->toolExecutionToArray($execution),
                $this->toolExecutions->all(),
            ),
            'finishReason' => $this->finishReason?->value,
            'usage' => $this->usage->toArray(),
            'startedAt' => $this->startedAt->format(DateTimeImmutable::ATOM),
            'completedAt' => $this->completedAt->format(DateTimeImmutable::ATOM),
            'duration' => $this->duration,
            'stopSignal' => $this->stopSignal?->toArray(),
            'errors' => array_map(
                fn (Throwable $error): array => [
                    'message' => $this->digestOrPassthrough($error->getMessage()),
                    'class' => get_class($error),
                ],
                $this->errors->all(),
            ),
            'hasErrors' => $this->hasErrors(),
        ];
    }

    /**
     * Hydrates from a previously serialized step. Digested payloads (arguments,
     * results) hydrate as the digest arrays themselves - there is no way back to the
     * original value, and none is attempted. `$policy` governs what happens on a
     * subsequent `toArray()` call: values already in digest shape always pass
     * through unchanged (never digested twice, regardless of policy), while any
     * other value - e.g. a verbatim payload sent by a third-party remote target
     * that never constructed a policy of its own - is digested under `$policy`
     * (default `safe()`) exactly as if it had come from a local run.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?EvalTracePolicy $policy = null): self {
        $requestedToolCalls = [];
        foreach (self::arrayOf($data['requestedToolCalls'] ?? null) as $entry) {
            $requestedToolCalls[] = new ToolCall(
                name: is_string($entry['name'] ?? null) ? $entry['name'] : '',
                arguments: is_array($entry['arguments'] ?? null) ? $entry['arguments'] : [],
                id: (is_string($entry['id'] ?? null) && $entry['id'] !== '') ? $entry['id'] : null,
            );
        }

        $toolExecutions = [];
        foreach (self::arrayOf($data['toolExecutions'] ?? null) as $entry) {
            $toolExecutions[] = self::toolExecutionFromArray($entry);
        }

        return new self(
            id: (is_string($data['id'] ?? null) && $data['id'] !== '') ? new AgentStepId($data['id']) : AgentStepId::generate(),
            turn: is_int($data['turn'] ?? null) ? $data['turn'] : 0,
            index: is_int($data['index'] ?? null) ? $data['index'] : 0,
            type: is_string($data['type'] ?? null) ? (AgentStepType::tryFrom($data['type']) ?? AgentStepType::FinalResponse) : AgentStepType::FinalResponse,
            outputMessages: is_array($data['outputMessages'] ?? null) ? Messages::fromArray($data['outputMessages']) : Messages::empty(),
            requestedToolCalls: new ToolCalls(...$requestedToolCalls),
            toolExecutions: new ToolExecutions(...$toolExecutions),
            finishReason: is_string($data['finishReason'] ?? null) ? InferenceFinishReason::tryFrom($data['finishReason']) : null,
            usage: is_array($data['usage'] ?? null) ? InferenceUsage::fromArray($data['usage']) : InferenceUsage::none(),
            stopSignal: is_array($data['stopSignal'] ?? null) ? StopSignal::fromArray($data['stopSignal']) : null,
            errors: ErrorList::fromArray(is_array($data['errors'] ?? null) ? $data['errors'] : []),
            startedAt: self::parseDate($data['startedAt'] ?? null),
            completedAt: self::parseDate($data['completedAt'] ?? null),
            duration: is_numeric($data['duration'] ?? null) ? (float) $data['duration'] : 0.0,
            policy: $policy ?? EvalTracePolicy::safe(),
        );
    }

    // INTERNAL ////////////////////////////////////////////////

    /** @return array<string, mixed> */
    private function toolCallToArray(ToolCall $call): array {
        return [
            'id' => $call->idString(),
            'name' => $call->name(),
            'arguments' => $this->digestOrPassthrough($call->arguments()),
        ];
    }

    /** @return array<string, mixed> */
    private function toolExecutionToArray(ToolExecution $execution): array {
        $hasError = $execution->hasError();
        return [
            'id' => $execution->id()->value,
            'name' => $execution->name(),
            'toolCallId' => $execution->toolCall()->idString(),
            'arguments' => $this->digestOrPassthrough($execution->args()),
            'hasError' => $hasError,
            'error' => $hasError ? $this->digestOrPassthrough($execution->errorMessage()) : null,
            'result' => $hasError ? null : $this->digestOrPassthrough($execution->value()),
            'startedAt' => $execution->startedAt()->format(DateTimeImmutable::ATOM),
            'completedAt' => $execution->completedAt()->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * A value already in digest shape passes through unchanged (never digested
     * twice); otherwise it is digested or kept verbatim per `$this->policy`.
     */
    private function digestOrPassthrough(mixed $value): mixed {
        return match (true) {
            EvalTracePolicy::isDigest($value) => $value,
            $this->policy->isFull() => $value,
            default => $this->policy->digest($value),
        };
    }

    private static function toolExecutionFromArray(array $data): ToolExecution {
        $toolCall = new ToolCall(
            name: is_string($data['name'] ?? null) ? $data['name'] : '',
            arguments: is_array($data['arguments'] ?? null) ? $data['arguments'] : [],
            id: (is_string($data['toolCallId'] ?? null) && $data['toolCallId'] !== '') ? $data['toolCallId'] : null,
        );
        $hasError = (bool) ($data['hasError'] ?? false);
        $result = match (true) {
            $hasError => Result::failure(new ToolExecutionException(
                is_string($data['error'] ?? null) ? $data['error'] : 'Unknown execution error',
                $toolCall,
            )),
            default => Result::success($data['result'] ?? null),
        };

        return new ToolExecution(
            toolCall: $toolCall,
            result: $result,
            startedAt: self::parseDate($data['startedAt'] ?? null),
            completedAt: self::parseDate($data['completedAt'] ?? null),
            id: (is_string($data['id'] ?? null) && $data['id'] !== '') ? new ToolExecutionId($data['id']) : null,
        );
    }

    /** @return list<array<string, mixed>> */
    private static function arrayOf(mixed $value): array {
        if (!is_array($value)) {
            return [];
        }
        $entries = [];
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    private static function parseDate(mixed $value): DateTimeImmutable {
        return match (true) {
            $value instanceof DateTimeImmutable => $value,
            is_string($value) && $value !== '' => new DateTimeImmutable($value),
            default => new DateTimeImmutable(),
        };
    }
}
