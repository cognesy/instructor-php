<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step\Contracts;

use Cognesy\Polyglot\Inference\Data\Usage;

interface HasStepUsage
{
    public function usage(): Usage;
}