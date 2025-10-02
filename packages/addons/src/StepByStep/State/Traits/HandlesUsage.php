<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Traits;

use Cognesy\Polyglot\Inference\Data\Usage;

trait HandlesUsage
{
    protected readonly Usage $usage;

    public function usage(): Usage {
        return $this->usage;
    }

    public function withUsage(Usage $usage): static {
        return $this->with(usage: $usage);
    }

    public function withAccumulatedUsage(Usage $usage): static {
        return $this->withUsage($this->usage->withAccumulated($usage));
    }
}