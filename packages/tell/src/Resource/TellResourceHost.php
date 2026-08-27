<?php

declare(strict_types=1);

namespace Cognesy\Tell\Resource;

use Closure;
use Cognesy\Tell\Contracts\CanApproveTellShellJobs;
use Cognesy\Tell\Contracts\CanManageTellShellJobs;
use Cognesy\Tell\Contracts\CanObserveTellResources;
use Cognesy\Tell\Resource\Exception\TellResourceHostDisposedException;
use Cognesy\Tell\Resource\NullTellResourceObserver;
use Cognesy\Tell\Resource\TellShellJobApprovals;
use Cognesy\Tell\Shell\TellShellJobPolicy;
use CordisPhp\Runtime\Fiber;
use CordisPhp\Runtime\Runtime;

/** Opt-in host for resources that must remain alive beyond one Tell call. */
final class TellResourceHost
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
        ?CanObserveTellResources $observer = null,
    ): TellResourceHostBuilder {
        return new TellResourceHostBuilder(
            projectDirectory: $project,
            policy: $policy ?? new TellShellJobPolicy(),
            approval: $approval ?? TellShellJobApprovals::denyAll(),
            observer: $observer ?? new NullTellResourceObserver(),
        );
    }

    public function jobs(): CanManageTellShellJobs
    {
        $this->assertActive();

        return $this->manager;
    }

    /** @return list<TellResourceHealth> */
    public function health(): array
    {
        $this->assertActive();

        return array_map(
            static fn (Fiber $fiber): TellResourceHealth => new TellResourceHealth(
                module: $fiber->label(),
                state: $fiber->state()->value,
                missing: $fiber->missing(),
                error: $fiber->error() === null ? null : $fiber->error()::class,
            ),
            $this->runtime->fibers(),
        );
    }

    public function dispose(): void
    {
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

    private function assertActive(): void
    {
        if ($this->disposed) {
            throw new TellResourceHostDisposedException();
        }
    }
}
