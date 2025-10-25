<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Utils;

use Cognesy\Utils\Sandbox\Enums\TimeoutReason;

final class TimeoutTracker
{
    private float $startedAt = 0.0;
    private float $lastActivityAt = 0.0;
    private ?float $wallDeadline = null;
    private bool $timedOut = false;
    private ?TimeoutReason $reason = null;

    public function __construct(
        private readonly int $wallSeconds,
        private readonly ?int $idleSeconds = null,
    ) {}

    public function start(): void {
        $now = microtime(true);
        $this->startedAt = $now;
        $this->lastActivityAt = $now;
        $this->wallDeadline = $now + max(1, $this->wallSeconds);
        $this->timedOut = false;
        $this->reason = null;
    }

    public function onActivity(): void {
        $this->lastActivityAt = microtime(true);
    }

    public function shouldTerminate(): bool {
        $now = microtime(true);
        if ($this->wallDeadline !== null && $now >= $this->wallDeadline) {
            $this->timedOut = true;
            $this->reason = TimeoutReason::WALL;
            return true;
        }
        if ($this->idleSeconds !== null) {
            $idleFor = $now - $this->lastActivityAt;
            if ($idleFor >= $this->idleSeconds) {
                $this->timedOut = true;
                $this->reason = TimeoutReason::IDLE;
                return true;
            }
        }
        return false;
    }

    public function timedOut(): bool {
        return $this->timedOut;
    }

    public function reason(): ?TimeoutReason {
        return $this->reason;
    }

    public function startedAt(): float {
        return $this->startedAt;
    }

    public function duration(): float {
        return microtime(true) - $this->startedAt;
    }
}
