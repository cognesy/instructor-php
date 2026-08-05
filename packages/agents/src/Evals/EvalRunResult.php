<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Countable;
use IteratorAggregate;
use Override;
use Traversable;

/** @implements IteratorAggregate<int, EvalResult> */
final readonly class EvalRunResult implements Countable, IteratorAggregate
{
    /** @var list<EvalResult> */
    private array $results;

    /** @var list<string> */
    private array $reporterErrors;

    /** @param list<string> $reporterErrors */
    public function __construct(array $reporterErrors, private bool $strict, EvalResult ...$results) {
        $this->results = $results;
        $this->reporterErrors = $reporterErrors;
    }

    /** @return list<EvalResult> */
    public function all(): array {
        return $this->results;
    }

    /** @return list<string> */
    public function reporterErrors(): array {
        return $this->reporterErrors;
    }

    public function strict(): bool {
        return $this->strict;
    }

    #[Override]
    public function count(): int {
        return count($this->results);
    }

    #[Override]
    public function getIterator(): Traversable {
        yield from $this->results;
    }

    public function exitCode(?bool $strict = null): EvalExitCode {
        $strict ??= $this->strict;
        foreach ($this->results as $result) {
            if ($result->verdict() === EvalVerdict::Failed || ($strict && $result->verdict() === EvalVerdict::Scored)) {
                return EvalExitCode::EvalFailure;
            }
        }
        return $this->reporterErrors !== [] ? EvalExitCode::EvalFailure : EvalExitCode::Success;
    }

    /** @return array<string, mixed> */
    public function toArray(): array {
        $counts = ['passed' => 0, 'failed' => 0, 'scored' => 0, 'skipped' => 0];
        foreach ($this->results as $result) {
            $counts[$result->verdict()->value]++;
        }
        return [
            'strict' => $this->strict,
            'counts' => $counts,
            'reporterErrors' => $this->reporterErrors,
            'results' => array_map(static fn (EvalResult $result): array => $result->toArray(), $this->results),
        ];
    }
}
