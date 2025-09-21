<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Step\Contracts;

use Cognesy\Addons\Core\Step\StepInfo;

interface HasStepInfo
{
    public function id(): string;
    public function createdAt(): \DateTimeImmutable;
    public function stepInfo(): StepInfo;
}