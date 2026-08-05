<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final readonly class EvalTurn
{
    public function __construct(
        private int $index,
        private string $message,
        private AgentRun $run,
    ) {}

    public function index(): int {
        return $this->index;
    }

    public function message(): string {
        return $this->message;
    }

    public function run(): AgentRun {
        return $this->run;
    }

    public function reply(): string {
        return $this->run->reply();
    }
}
