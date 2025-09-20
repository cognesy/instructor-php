<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\StepContracts;

use Cognesy\Polyglot\Inference\Data\Usage;

interface HasStepUsage
{
    public function usage(): Usage;
}