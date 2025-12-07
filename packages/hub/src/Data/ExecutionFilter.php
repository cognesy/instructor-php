<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Data;

use Cognesy\InstructorHub\Contracts\CanFilterExamples;

class ExecutionFilter implements CanFilterExamples
{
    public function __construct(
        public readonly FilterMode $mode,
        public readonly ?\DateInterval $staleThreshold = null,
        public readonly ?\DateInterval $errorThreshold = null,
    ) {}

    public static function all(): self
    {
        return new self(FilterMode::ALL);
    }

    public static function errorsOnly(?\DateInterval $threshold = null): self
    {
        return new self(FilterMode::ERRORS_ONLY, errorThreshold: $threshold);
    }

    public static function staleOnly(?\DateInterval $threshold = null): self
    {
        return new self(FilterMode::STALE_ONLY, staleThreshold: $threshold);
    }

    public static function pendingOnly(): self
    {
        return new self(FilterMode::PENDING_ONLY);
    }

    public static function notCompleted(): self
    {
        return new self(FilterMode::NOT_COMPLETED);
    }

    public static function interrupted(): self
    {
        return new self(FilterMode::INTERRUPTED_ONLY);
    }

    public static function fromMode(FilterMode $mode): self
    {
        return new self($mode);
    }

    public function shouldExecute(ExampleExecutionStatus $status): bool
    {
        return match($this->mode) {
            FilterMode::ALL => true,
            FilterMode::ERRORS_ONLY => $status->isError() || ($this->errorThreshold !== null && $status->hasRecentError($this->errorThreshold)),
            FilterMode::STALE_ONLY => $status->isStale(),
            FilterMode::PENDING_ONLY => $status->isPending(),
            FilterMode::NOT_COMPLETED => !$status->isCompleted(),
            FilterMode::INTERRUPTED_ONLY => $status->wasInterrupted(),
        };
    }

    #[\Override]
    public function getDescription(): string
    {
        return $this->mode->getDescription();
    }
}
