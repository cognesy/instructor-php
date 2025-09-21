<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Step\Traits;

use Cognesy\Addons\StepByStep\Step\StepInfo;
use DateTimeImmutable;

trait HandlesStepInfo
{
    private readonly StepInfo $stepInfo;

    public function stepInfo(): StepInfo {
        return $this->stepInfo;
    }

    public function id(): string {
        return $this->stepInfo->id();
    }

    public function createdAt(): DateTimeImmutable {
        return $this->stepInfo->createdAt();
    }
}