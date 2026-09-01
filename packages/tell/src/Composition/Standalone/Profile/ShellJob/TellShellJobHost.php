<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Profile\ShellJob;

use Cognesy\Tell\Core\Contract\ShellJob\CanManageTellShellJobs;
use Cognesy\Tell\Data\TellShellJobHealth;
use Cognesy\Tell\Capability\ShellJob\Process\TellShellJobEventEmitter;
use Cognesy\Tell\Capability\ShellJob\Process\TellShellJobs;

/** Opt-in host for shell jobs that must remain alive beyond one Tell call. */
final class TellShellJobHost
{
    private bool $disposed = false;

    public function __construct(
        private readonly TellShellJobs $manager,
        private readonly TellShellJobEventEmitter $events,
    ) {}

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
