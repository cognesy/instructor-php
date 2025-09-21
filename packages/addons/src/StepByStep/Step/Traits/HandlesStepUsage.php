<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step\Traits;

use Cognesy\Polyglot\Inference\Data\Usage;

trait HandlesStepUsage
{
    protected readonly Usage $usage;

    public function usage(): Usage {
        return $this->usage;
    }
}