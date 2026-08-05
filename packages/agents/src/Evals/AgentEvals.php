<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, AgentEval> */
final readonly class AgentEvals implements Countable, IteratorAggregate
{
    /** @var list<AgentEval> */
    private array $evals;

    public function __construct(AgentEval ...$evals) {
        $this->evals = $evals;
    }

    public static function none(): self {
        return new self();
    }

    public function with(AgentEval ...$evals): self {
        return new self(...[...$this->evals, ...$evals]);
    }

    /** @return list<AgentEval> */
    public function all(): array {
        return $this->evals;
    }

    #[Override]
    public function count(): int {
        return count($this->evals);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->evals;
    }

    public function filtered(?string $glob = null, ?EvalTags $required = null, ?EvalTags $excluded = null): self {
        $selected = [];
        foreach ($this->evals as $eval) {
            if ($glob !== null && !fnmatch($glob, $eval->id() ?? '')) {
                continue;
            }
            if (!self::hasAll($eval->tags(), $required ?? EvalTags::none())) {
                continue;
            }
            if (self::hasAny($eval->tags(), $excluded ?? EvalTags::none())) {
                continue;
            }
            $selected[] = $eval;
        }
        return new self(...$selected);
    }

    private static function hasAll(EvalTags $actual, EvalTags $required): bool {
        foreach ($required as $tag) {
            if (!$actual->has($tag)) {
                return false;
            }
        }
        return true;
    }

    private static function hasAny(EvalTags $actual, EvalTags $excluded): bool {
        foreach ($excluded as $tag) {
            if ($actual->has($tag)) {
                return true;
            }
        }
        return false;
    }
}
