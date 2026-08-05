<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final readonly class EvalRunOptions
{
    public function __construct(
        private ?string $filter = null,
        private EvalTags $tags = new EvalTags(),
        private EvalTags $excludedTags = new EvalTags(),
        private bool $strict = false,
        private bool $skipReport = false,
        private bool $verbose = false,
        private ?float $timeout = null,
    ) {}

    public static function default(): self {
        return new self();
    }

    public function withFilter(?string $filter): self {
        return new self($filter, $this->tags, $this->excludedTags, $this->strict, $this->skipReport, $this->verbose, $this->timeout);
    }

    public function withTags(EvalTags $tags): self {
        return new self($this->filter, $tags, $this->excludedTags, $this->strict, $this->skipReport, $this->verbose, $this->timeout);
    }

    public function withExcludedTags(EvalTags $tags): self {
        return new self($this->filter, $this->tags, $tags, $this->strict, $this->skipReport, $this->verbose, $this->timeout);
    }

    public function withStrict(bool $strict): self {
        return new self($this->filter, $this->tags, $this->excludedTags, $strict, $this->skipReport, $this->verbose, $this->timeout);
    }

    public function withSkipReport(bool $skip): self {
        return new self($this->filter, $this->tags, $this->excludedTags, $this->strict, $skip, $this->verbose, $this->timeout);
    }

    public function withVerbose(bool $verbose): self {
        return new self($this->filter, $this->tags, $this->excludedTags, $this->strict, $this->skipReport, $verbose, $this->timeout);
    }

    public function withTimeout(?float $timeout): self {
        return new self($this->filter, $this->tags, $this->excludedTags, $this->strict, $this->skipReport, $this->verbose, $timeout);
    }

    public function filter(): ?string {
        return $this->filter;
    }

    public function tags(): EvalTags {
        return $this->tags;
    }

    public function excludedTags(): EvalTags {
        return $this->excludedTags;
    }

    public function strict(): bool {
        return $this->strict;
    }

    public function skipReport(): bool {
        return $this->skipReport;
    }

    public function verbose(): bool {
        return $this->verbose;
    }

    public function timeout(): ?float {
        return $this->timeout;
    }
}
