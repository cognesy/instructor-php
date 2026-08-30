<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

use Cognesy\Tell\Contracts\CanApproveTellShellJobs;
use Cognesy\Tell\Contracts\CanManageTellShellJobs;
use Cognesy\Tell\Contracts\CanObserveTellShellJobs;
use Cognesy\Tell\Data\TellShellJobHealth;
use Cognesy\Tell\Shell\Exception\TellShellJobHostDisposedException;

/** Opt-in host for shell jobs that must remain alive beyond one Tell call. */
final class TellShellJobHost
{
    private bool $disposed = false;

    public function __construct(
        private readonly TellShellJobs $manager,
        private readonly TellShellJobEventEmitter $events,
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

        return [
            new TellShellJobHealth(module: 'shell.jobs', state: 'active'),
            ...$this->manager->health(),
        ];
    }

    public function dispose(): void {
        if ($this->disposed) {
            return;
        }
        $this->disposed = true;
        try {
            $this->manager->dispose();
        } finally {
            $this->events->emit('module.disposed', 'shell.jobs', [
                'previous' => 'active',
                'current' => 'disposed',
            ], 'disposed');
        }
    }

    private function assertActive(): void {
        if ($this->disposed) {
            throw new TellShellJobHostDisposedException();
        }
    }
}
