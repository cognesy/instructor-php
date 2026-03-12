<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step\Traits;

use Cognesy\Polyglot\Inference\Data\InferenceUsage;

trait HandlesStepUsage
{
    protected readonly InferenceUsage $usage;

    public function usage(): InferenceUsage {
        return $this->usage;
    }
}