<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, CanReportAgentEvals> */
final readonly class EvalReporters implements IteratorAggregate
{
    /** @var list<CanReportAgentEvals> */
    private array $reporters;

    public function __construct(CanReportAgentEvals ...$reporters) {
        $unique = [];
        foreach ($reporters as $reporter) {
            $unique[$reporter->id()] = $reporter;
        }
        $this->reporters = array_values($unique);
    }

    public static function none(): self {
        return new self();
    }

    public function with(CanReportAgentEvals $reporter): self {
        $reporters = [...$this->reporters, $reporter];
        return new self(...$reporters);
    }

    public function withVerboseConsole(): self {
        $reporters = array_map(
            static fn (CanReportAgentEvals $reporter): CanReportAgentEvals => $reporter instanceof ConsoleEvalReporter ? $reporter->withVerbose(true) : $reporter,
            $this->reporters,
        );
        return new self(...$reporters);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->reporters;
    }
}
