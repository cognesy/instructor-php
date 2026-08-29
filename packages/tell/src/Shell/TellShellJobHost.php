<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

use Closure;
use Cognesy\Tell\Contracts\CanApproveTellShellJobs;
use Cognesy\Tell\Contracts\CanManageTellShellJobs;
use Cognesy\Tell\Contracts\CanObserveTellShellJobs;
use Cognesy\Tell\Data\TellShellJobHealth;
use Cognesy\Tell\Shell\Exception\TellShellJobHostDisposedException;
use CordisPhp\Runtime\Fiber;
use CordisPhp\Runtime\Runtime;

/** Opt-in host for shell jobs that must remain alive beyond one Tell call. */
final class TellShellJobHost
{
    private bool $disposed = false;

    /** @param Closure(): void $unsubscribe */
    public function __construct(
        private readonly Runtime $runtime,
        private readonly CanManageTellShellJobs $manager,
        private readonly Closure $unsubscribe,
    ) {}

    public static function shellJobs(
        string $project,
        ?TellShellJobPolicy $policy = null,
        ?CanApproveTellShellJobs $approval = null,
        ?CanObserveTellShellJobs $observer = null,
    ): TellShellJobHostBuilder {
        return new TellShellJobHostBuilder(
            projectDirectory: $project,
            policy: $policy ?? new TellShellJobPolicy(),
            approval: $approval ?? TellShellJobApprovals::denyAll(),
            observer: $observer ?? new NullTellShellJobObserver(),
        );
    }

    public function jobs(): CanManageTellShellJobs {
        $this->assertActive();

        return $this->manager;
    }

    /** @return list<TellShellJobHealth> */
    public function health(): array {
        $this->assertActive();

        return array_map(
            static fn (Fiber $fiber): TellShellJobHealth => new TellShellJobHealth(
                module: $fiber->label(),
                state: $fiber->state()->value,
                missing: $fiber->missing(),
                error: $fiber->error() === null ? null : $fiber->error()::class,
            ),
            $this->runtime->fibers(),
        );
    }

    public function dispose(): void {
        if ($this->disposed) {
            return;
        }
        $this->disposed = true;
        try {
            $this->runtime->dispose();
        } finally {
            ($this->unsubscribe)();
        }
    }

    private function assertActive(): void {
        if ($this->disposed) {
            throw new TellShellJobHostDisposedException();
        }
    }
}
