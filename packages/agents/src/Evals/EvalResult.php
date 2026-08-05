<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final readonly class EvalResult
{
    public function __construct(
        private string $id,
        private string $description,
        private EvalVerdict $verdict,
        private AssertionResults $assertions,
        private AgentRun $run,
        private float $duration,
        private ?string $error = null,
        private ?string $skipReason = null,
        private EvalLogs $logs = new EvalLogs(),
    ) {}

    public function id(): string {
        return $this->id;
    }

    public function description(): string {
        return $this->description;
    }

    public function verdict(): EvalVerdict {
        return $this->verdict;
    }

    public function assertions(): AssertionResults {
        return $this->assertions;
    }

    public function run(): AgentRun {
        return $this->run;
    }

    public function duration(): float {
        return $this->duration;
    }

    public function error(): ?string {
        return $this->error;
    }

    public function skipReason(): ?string {
        return $this->skipReason;
    }

    public function logs(): EvalLogs {
        return $this->logs;
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'verdict' => $this->verdict->value,
            'duration' => $this->duration,
            'error' => $this->error,
            'skipReason' => $this->skipReason,
            'logs' => array_map(static fn (EvalLog $log): array => $log->toArray(), $this->logs->all()),
            'assertions' => array_map(static fn (AssertionResult $result): array => $result->toArray(), $this->assertions->all()),
            'run' => $this->run->toArray(),
        ];
    }
}
