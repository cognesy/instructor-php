<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Data\ToolExecution;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Profile\LLMConfigProfile;
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
        private ?LLMConfigProfile $llmProfile = null,
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
        ?LLMConfigProfile $llmProfile = null,
    ): self {
        $policy ??= EvalTracePolicy::safe();
        $turn ??= ($previous?->turns ?? 0) + 1;
        $llmProfile ??= $previous?->llmProfile;

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
            llmProfile: $llmProfile,
        );
    }

    public static function empty(): self {
        return new self();
    }

    /**
     * Hydrates a run from a previously serialized snapshot. `$policy` (default
     * `safe()`) governs what happens to tool payloads on a subsequent `toArray()`
     * call: values already in digest shape always pass through unchanged, while
     * verbatim values - including ones sent by a third-party remote target that
     * never constructed a policy of its own - are digested under `$policy` exactly
     * as if they had come from a local run. This is what keeps the HTTP path safe
     * by default instead of silently degrading to `full()`.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data, ?EvalTracePolicy $policy = null): self {
        $policy ??= EvalTracePolicy::safe();
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
        $steps = is_array($data['steps'] ?? null) ? EvalSteps::fromArray($data['steps'], $policy) : EvalSteps::none();
        $stopSignal = is_array($data['stopSignal'] ?? null) ? StopSignal::fromArray($data['stopSignal']) : null;
        $llmData = $data['llmProfile'] ?? null;
        $llmProfile = is_array($llmData) ? new LLMConfigProfile(
            driver: is_string($llmData['driver'] ?? null) ? $llmData['driver'] : '',
            model: is_string($llmData['model'] ?? null) ? $llmData['model'] : '',
            maxTokens: is_int($llmData['maxTokens'] ?? null) ? $llmData['maxTokens'] : 0,
            contextLength: is_int($llmData['contextLength'] ?? null) ? $llmData['contextLength'] : 0,
            maxOutputLength: is_int($llmData['maxOutputLength'] ?? null) ? $llmData['maxOutputLength'] : 0,
        ) : null;
        return new self(
            reply: is_string($data['reply'] ?? null) ? $data['reply'] : '',
            status: $status,
            tools: new EvalToolExecutions(...$tools),
            events: EvalEvents::none(),
            turns: is_int($data['turns'] ?? null) ? $data['turns'] : 0,
            errors: is_string($data['errors'] ?? null) ? $data['errors'] : '',
            steps: $steps,
            stopSignal: $stopSignal,
            policy: $policy,
            llmProfile: $llmProfile,
        );
    }

    /**
     * The `tools` field is a legacy aggregate view kept for `EvalContext`'s tool-name
     * and tool-count assertions; `steps` is the source of truth for the trajectory.
     * It is digested under the same `EvalTracePolicy` as `EvalStep::toArray()` so a
     * tool argument, result, or failed execution's error message never leaks through
     * this side channel either.
     *
     * `errors` (the run-level newline-joined message string accumulated from every
     * step's `AgentState::errors()`) is digested the same way when non-empty, for
     * the same reason: it re-embeds the same exception messages that
     * `tools[].error` and `steps[].errors[].message` carry, so leaving it verbatim
     * would reopen the exact leak those two fields are digested to close. `errors()`
     * (the accessor) still returns the raw string - `EvalContext::noFailedActions()`
     * reads it as an in-memory value, never the serialized form, so digesting here
     * does not affect assertion behaviour.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array {
        return [
            'reply' => $this->reply,
            'status' => $this->status?->value,
            'tools' => array_map(fn (ToolExecution $tool): array => $this->toolExecutionToArray($tool), $this->tools->all()),
            'turns' => $this->turns,
            'errors' => $this->errors === '' ? '' : $this->digestOrPassthrough($this->errors),
            'steps' => $this->steps->toArray(),
            'stopSignal' => $this->stopSignal?->toArray(),
            'llmProfile' => $this->llmProfile?->toArray(),
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

    /**
     * The target/judge LLM configuration this run executed under, when the
     * underlying loop resolved one (`AgentLoop::profile()->llm`). Null for runs
     * built from a driver that never resolved an `LLMConfig` (e.g. some test
     * doubles) or for remote/HTTP runs whose payload didn't supply one - never
     * fabricated when unavailable.
     */
    public function llmProfile(): ?LLMConfigProfile {
        return $this->llmProfile;
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
                'arguments' => $this->digestOrPassthrough($tool->args()),
            ],
            'result' => $hasError ? null : $this->digestOrPassthrough($tool->value()),
            // Digested exactly like `arguments`/`result`: an exception message
            // routinely embeds the offending input (e.g. "Invalid card number
            // 4111...", "HTTP 401 for ...?key=sk-live-..."), so it is the
            // payload channel most likely to be malformed or sensitive - it
            // gets no exemption from `safe()` just because it's error text.
            'error' => $hasError ? $this->digestOrPassthrough($tool->errorMessage()) : null,
            'startedAt' => $tool->startedAt()->format(DateTimeImmutable::ATOM),
            'completedAt' => $tool->completedAt()->format(DateTimeImmutable::ATOM),
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
}
