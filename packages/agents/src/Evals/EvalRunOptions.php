<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use InvalidArgumentException;

final readonly class EvalRunOptions
{
    /**
     * @param int $repeat How many trials each selected case runs. Every trial
     *        gets a FRESH session, and the case verdict is resolved k-of-N
     *        against `$passRate`. Repetition only measures TARGET variance when
     *        the judge is pinned to a deterministic temperature - install
     *        `UseJudgeInference` (or otherwise fix temperature) in the judge
     *        builder, since `AgentLoopJudge` never installs it for you. A judge
     *        left at the provider default makes the reported judge mean and
     *        deviation partly judge noise.
     * @param float $passRate Fraction of trials that must pass, in (0, 1].
     *        Requesting a pass rate makes it an explicit gate: a case that
     *        misses it fails, even when every individual shortfall was a soft
     *        assertion.
     */
    public function __construct(
        private ?string $filter = null,
        private EvalTags $tags = new EvalTags(),
        private EvalTags $excludedTags = new EvalTags(),
        private bool $strict = false,
        private bool $skipReport = false,
        private bool $verbose = false,
        private ?float $timeout = null,
        private int $repeat = 1,
        private float $passRate = 1.0,
    ) {
        if ($repeat < 1) {
            throw new InvalidArgumentException('Repeat must be at least 1 trial.');
        }
        if (!is_finite($passRate) || $passRate <= 0.0 || $passRate > 1.0) {
            throw new InvalidArgumentException('Pass rate must be greater than 0 and at most 1.');
        }
    }

    public static function default(): self {
        return new self();
    }

    public function withFilter(?string $filter): self {
        return new self($filter, $this->tags, $this->excludedTags, $this->strict, $this->skipReport, $this->verbose, $this->timeout, $this->repeat, $this->passRate);
    }

    public function withTags(EvalTags $tags): self {
        return new self($this->filter, $tags, $this->excludedTags, $this->strict, $this->skipReport, $this->verbose, $this->timeout, $this->repeat, $this->passRate);
    }

    public function withExcludedTags(EvalTags $tags): self {
        return new self($this->filter, $this->tags, $tags, $this->strict, $this->skipReport, $this->verbose, $this->timeout, $this->repeat, $this->passRate);
    }

    public function withStrict(bool $strict): self {
        return new self($this->filter, $this->tags, $this->excludedTags, $strict, $this->skipReport, $this->verbose, $this->timeout, $this->repeat, $this->passRate);
    }

    public function withSkipReport(bool $skip): self {
        return new self($this->filter, $this->tags, $this->excludedTags, $this->strict, $skip, $this->verbose, $this->timeout, $this->repeat, $this->passRate);
    }

    public function withVerbose(bool $verbose): self {
        return new self($this->filter, $this->tags, $this->excludedTags, $this->strict, $this->skipReport, $verbose, $this->timeout, $this->repeat, $this->passRate);
    }

    public function withTimeout(?float $timeout): self {
        return new self($this->filter, $this->tags, $this->excludedTags, $this->strict, $this->skipReport, $this->verbose, $timeout, $this->repeat, $this->passRate);
    }

    public function withRepeat(int $repeat): self {
        return new self($this->filter, $this->tags, $this->excludedTags, $this->strict, $this->skipReport, $this->verbose, $this->timeout, $repeat, $this->passRate);
    }

    public function withPassRate(float $passRate): self {
        return new self($this->filter, $this->tags, $this->excludedTags, $this->strict, $this->skipReport, $this->verbose, $this->timeout, $this->repeat, $passRate);
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

    public function repeat(): int {
        return $this->repeat;
    }

    public function passRate(): float {
        return $this->passRate;
    }
}
