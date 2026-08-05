<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use DateTimeImmutable;

/** Immutable accumulated projection of an eval session. */
final readonly class AgentRun
{
    private EvalTracePolicy $policy;

    public function __construct(
        private string $reply = '',
        private ?ExecutionStatus $status = null,
        private EvalToolExecutions $tools = new EvalToolExecutions(),
        private EvalEvents $events = new EvalEvents(),
        private int $turns = 0,
        private string $errors = '',
        private EvalSteps $steps = new EvalSteps(),
        private ?StopSignal $stopSignal = null,
        ?EvalTracePolicy $policy = null,
    ) {
        $this->policy = $policy ?? EvalTracePolicy::safe();
    }

    /** @param list<object> $events */
    public static function fromState(
        AgentState $state,
        array $events = [],
        ?self $previous = null,
        ?EvalTracePolicy $policy = null,
        ?int $turn = null,
    ): self {
        $policy ??= EvalTracePolicy::safe();
        $turn ??= ($previous?->turns ?? 0) + 1;

        $tools = $previous?->tools ?? EvalToolExecutions::none();
        $steps = $previous?->steps ?? EvalSteps::none();

        // AgentState::stepExecutions() returns only the CURRENT turn's steps -
        // AgentLoop::ensureNextExecution() resets execution state once status is
        // Completed/Stopped/Failed - so accumulating onto $previous here never double
        // counts. Pinned by AgentRunTest's three-turn accumulation test.
        $index = $steps->count();
        $newSteps = [];
        foreach ($state->stepExecutions()->all() as $stepExecution) {
            $tools = $tools->with(...$stepExecution->step()->toolExecutions()->all());
            $newSteps[] = EvalStep::fromStepExecution($stepExecution, $turn, $index, $policy);
            $index++;
        }

        return new self(
            reply: trim($state->finalResponse()->toString()),
            status: $state->status(),
            tools: $tools,
            events: ($previous?->events ?? EvalEvents::none())->with(...$events),
            turns: $turn,
            errors: $state->errors()->toMessagesString(),
            steps: $steps->with(...$newSteps),
            stopSignal: $state->stopSignal(),
            policy: $policy,
        );
    }

    public static function empty(): self {
        return new self();
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self {
        $toolData = $data['tools'] ?? [];
        $tools = [];
        if (is_array($toolData)) {
            foreach ($toolData as $entry) {
                if (is_array($entry)) {
                    $tools[] = ToolExecution::fromArray($entry);
                }
            }
        }
        $status = is_string($data['status'] ?? null) ? ExecutionStatus::tryFrom($data['status']) : null;
        $steps = is_array($data['steps'] ?? null) ? EvalSteps::fromArray($data['steps']) : EvalSteps::none();
        $stopSignal = is_array($data['stopSignal'] ?? null) ? StopSignal::fromArray($data['stopSignal']) : null;
        return new self(
            reply: is_string($data['reply'] ?? null) ? $data['reply'] : '',
            status: $status,
            tools: new EvalToolExecutions(...$tools),
            events: EvalEvents::none(),
            turns: is_int($data['turns'] ?? null) ? $data['turns'] : 0,
            errors: is_string($data['errors'] ?? null) ? $data['errors'] : '',
            steps: $steps,
            stopSignal: $stopSignal,
            // Hydrated data is already in its final serialized form (digested or
            // verbatim, per whatever policy produced it) - it must not be digested a
            // second time on a subsequent toArray() call.
            policy: EvalTracePolicy::full(),
        );
    }

    /**
     * The `tools` field is a legacy aggregate view kept for `EvalContext`'s tool-name
     * and tool-count assertions; `steps` is the source of truth for the trajectory.
     * It is digested under the same `EvalTracePolicy` as `EvalStep::toArray()` so a
     * tool argument or result never leaks through this side channel either.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'reply' => $this->reply,
            'status' => $this->status?->value,
            'tools' => array_map(fn (ToolExecution $tool): array => $this->toolExecutionToArray($tool), $this->tools->all()),
            'turns' => $this->turns,
            'errors' => $this->errors,
            'steps' => $this->steps->toArray(),
            'stopSignal' => $this->stopSignal?->toArray(),
        ];
    }

    public function reply(): string {
        return $this->reply;
    }

    public function status(): ?ExecutionStatus {
        return $this->status;
    }

    public function succeeded(): bool {
        return $this->status === ExecutionStatus::Completed;
    }

    public function tools(): EvalToolExecutions {
        return $this->tools;
    }

    public function events(): EvalEvents {
        return $this->events;
    }

    public function turns(): int {
        return $this->turns;
    }

    public function errors(): string {
        return $this->errors;
    }

    /** All steps across all turns, in order. */
    public function steps(): EvalSteps {
        return $this->steps;
    }

    /** Usage accumulated across all steps of all turns. */
    public function usage(): InferenceUsage {
        return $this->steps->usage();
    }

    /** Step duration summed across all turns. */
    public function duration(): float {
        return $this->steps->duration();
    }

    public function stepCount(): int {
        return $this->steps->count();
    }

    /**
     * The LAST turn's resolved stop signal. This does NOT aggregate across turns -
     * per-turn signals live on `EvalStep::stopSignal()`.
     */
    public function stopSignal(): ?StopSignal {
        return $this->stopSignal;
    }

    // INTERNAL ////////////////////////////////////////////////

    /** @return array<string, mixed> */
    private function toolExecutionToArray(ToolExecution $tool): array {
        $hasError = $tool->hasError();
        return [
            'id' => $tool->id()->value,
            'tool_call' => [
                'id' => $tool->toolCall()->idString(),
                'name' => $tool->name(),
                'arguments' => $this->policy->isFull() ? $tool->args() : $this->policy->digest($tool->args()),
            ],
            'result' => match (true) {
                $hasError => null,
                $this->policy->isFull() => $tool->value(),
                default => $this->policy->digest($tool->value()),
            },
            'error' => $hasError ? $tool->errorMessage() : null,
            'startedAt' => $tool->startedAt()->format(DateTimeImmutable::ATOM),
            'completedAt' => $tool->completedAt()->format(DateTimeImmutable::ATOM),
        ];
    }
}
