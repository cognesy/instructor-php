<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Contracts;

use Cognesy\Addons\StepByStep\State\StateId;
use DateTimeImmutable;

interface HasStateInfo
{
    public function id(): StateId;
    public function startedAt(): DateTimeImmutable;
    public function updatedAt(): DateTimeImmutable;
}
