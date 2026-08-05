<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;

final readonly class AgentEvalSet
{
    public function __construct(private AgentEvals $evals) {}

    /** @param Closure(EvalDatasetRow): AgentEval $factory */
    public static function fromDataset(EvalDataset $dataset, Closure $factory): self {
        $evals = AgentEvals::none();
        foreach ($dataset as $row) {
            $evals = $evals->with($factory($row));
        }
        return new self($evals);
    }

    public static function of(AgentEval ...$evals): self {
        return new self(new AgentEvals(...$evals));
    }

    public function evals(): AgentEvals {
        return $this->evals;
    }
}
