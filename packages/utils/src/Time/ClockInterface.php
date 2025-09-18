<?php declare(strict_types=1);

namespace Cognesy\Utils\Time;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;
}

