<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Traits;

use Cognesy\Addons\StepByStep\State\StateInfo;
use DateTimeImmutable;

trait HandlesStateInfo
{
    protected readonly StateInfo $stateInfo;

    public function stateInfo(): StateInfo {
        return $this->stateInfo;
    }

    public function withStateInfo(StateInfo $stateInfo): static {
        return $this->with(stateInfo: $stateInfo);
    }

    public function id(): string {
        return $this->stateInfo->id();
    }

    public function startedAt(): DateTimeImmutable {
        return $this->stateInfo->startedAt();
    }

    public function updatedAt(): DateTimeImmutable {
        return $this->stateInfo->updatedAt();
    }
}