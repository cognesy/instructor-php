<?php

namespace Cognesy\Instructor\Traits;

use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
trait HandlesTimer
{
    protected float $startTime;
    protected float $endTime;

    protected function startTimer() : void {
        $this->startTime = microtime(true);
    }

    protected function stopTimer() : void {
        $this->endTime = microtime(true);
    }

    public function elapsedTime() : float {
        return round($this->endTime - $this->startTime, 4);
    }
}