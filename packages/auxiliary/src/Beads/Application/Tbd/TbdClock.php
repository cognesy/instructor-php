<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

class TbdClock
{
    public function now(): DateTimeImmutable {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function parse(?string $value): DateTimeImmutable {
        if ($value === null || $value === '') {
            return $this->now();
        }
        $parsed = DateTimeImmutable::createFromFormat(DateTimeImmutable::ATOM, $value);
        if ($parsed !== false) {
            return $parsed;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (\Exception $e) {
            throw new RuntimeException("Unable to parse date/time: {$value}", 0, $e);
        }
    }
}
