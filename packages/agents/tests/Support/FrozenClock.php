<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support;

use Cognesy\Utils\Time\ClockInterface;
use DateTimeImmutable;

final class FrozenClock implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable {
        return $this->now;
    }
}

