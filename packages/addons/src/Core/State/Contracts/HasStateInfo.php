<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\State\Contracts;

use DateTimeImmutable;

interface HasStateInfo
{
    public function id(): string;
    public function startedAt(): DateTimeImmutable;
    public function updatedAt(): DateTimeImmutable;
}