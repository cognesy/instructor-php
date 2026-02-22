<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step\Contracts;

use Cognesy\Addons\StepByStep\Step\StepId;
use Cognesy\Addons\StepByStep\Step\StepInfo;

interface HasStepInfo
{
    public function id(): StepId;
    public function createdAt(): \DateTimeImmutable;
    public function stepInfo(): StepInfo;
}
