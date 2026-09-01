<?php

declare(strict_types=1);

namespace Cognesy\Tell\Tests\Support;

use Closure;
use Cognesy\Tell\Core\Contract\Execution\CanRunTell;
use Throwable;

/** Test-only adapter from any composition host to observable SDK behavior. */
final readonly class TellHostConformanceHarness
{
    /**
     * @param Closure(): CanRunTell $runner
     * @param Closure(): void $dispose
     */
    public function __construct(
        private Closure $runner,
        private Closure $dispose,
    ) {}

    public function runner(): CanRunTell {
        return ($this->runner)();
    }

    public function dispose(): void {
        ($this->dispose)();
    }

    public function rejectsAccessAfterDisposal(): bool {
        try {
            $this->runner();

            return false;
        } catch (Throwable) {
            return true;
        }
    }
}
