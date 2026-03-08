<?php declare(strict_types=1);

namespace Cognesy\Utils\Profiler;

trait TracksObjectCreation
{
    private function trackObjectCreation(): void
    {
        ObjectCreationTrace::record($this);
    }
}
