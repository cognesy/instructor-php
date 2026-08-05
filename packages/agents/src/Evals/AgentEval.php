<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;
use InvalidArgumentException;

/** Immutable definition of one agent evaluation case. */
final readonly class AgentEval
{
    /** @param Closure(EvalContext): void $test */
    private function __construct(
        private string $description,
        private Closure $test,
        private EvalTags $tags,
        private ?string $id = null,
        private ?CanJudgeAgentEval $judge = null,
    ) {
        if (trim($description) === '') {
            throw new InvalidArgumentException('Eval description cannot be empty.');
        }
    }

    /** @param Closure(EvalContext): void $test */
    public static function define(
        string $description,
        Closure $test,
        ?EvalTags $tags = null,
        ?CanJudgeAgentEval $judge = null,
    ): self {
        return new self($description, $test, $tags ?? EvalTags::none(), judge: $judge);
    }

    public function withId(string $id): self {
        if (trim($id) === '') {
            throw new InvalidArgumentException('Eval id cannot be empty.');
        }
        return new self($this->description, $this->test, $this->tags, $id, $this->judge);
    }

    public function description(): string {
        return $this->description;
    }

    public function test(): Closure {
        return $this->test;
    }

    public function tags(): EvalTags {
        return $this->tags;
    }

    public function id(): ?string {
        return $this->id;
    }

    public function judge(): ?CanJudgeAgentEval {
        return $this->judge;
    }
}
