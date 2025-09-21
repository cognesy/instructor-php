<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Step\Traits;

use Cognesy\Addons\Core\Step\StepInfo;
use DateTimeImmutable;

trait HandlesStepInfo
{
    private readonly StepInfo $stepInfo;

    public function id(): string {
        return $this->stepInfo->id();
    }

    public function createdAt(): DateTimeImmutable {
        return $this->stepInfo->createdAt();
    }
}