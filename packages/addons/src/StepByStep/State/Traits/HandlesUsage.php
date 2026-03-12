<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Traits;

use Cognesy\Polyglot\Inference\Data\InferenceUsage;

trait HandlesUsage
{
    protected readonly InferenceUsage $usage;

    public function usage(): InferenceUsage {
        return $this->usage;
    }

    public function withUsage(InferenceUsage $usage): static {
        return $this->with(usage: $usage);
    }

    public function withAccumulatedUsage(InferenceUsage $usage): static {
        return $this->withUsage($this->usage->withAccumulated($usage));
    }
}