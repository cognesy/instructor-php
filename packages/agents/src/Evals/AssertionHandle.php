<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

final readonly class AssertionHandle
{
    public function __construct(
        private AssertionCollector $collector,
        private int $index,
    ) {}

    public function gate(): self {
        $this->collector->replace($this->index, $this->result()->withSeverity(AssertionSeverity::Gate));
        return $this;
    }

    public function soft(): self {
        $this->collector->replace($this->index, $this->result()->withSeverity(AssertionSeverity::Soft));
        return $this;
    }

    public function atLeast(float $threshold): self {
        $this->collector->replace($this->index, $this->result()->withThreshold($threshold));
        return $this;
    }

    public function label(string $label): self {
        $this->collector->replace($this->index, $this->result()->withLabel($label));
        return $this;
    }

    public function result(): AssertionResult {
        return $this->collector->at($this->index);
    }

    public function replace(AssertionResult $result): self {
        $this->collector->replace($this->index, $result);
        return $this;
    }
}
