<?php

declare(strict_types=1);

namespace Cognesy\Tell\Composition\Standalone\Profile\ShellJob;

use Cognesy\Tell\Core\Contract\ShellJob\CanApproveTellShellJobs;
use Cognesy\Tell\Core\Contract\ShellJob\CanObserveTellShellJobs;
use Cognesy\Tell\Capability\ShellJob\Process\TellShellJobEventEmitter;
use Cognesy\Tell\Capability\ShellJob\Process\TellShellJobs;
use Cognesy\Tell\Capability\ShellJob\Process\TellShellJobPolicy;
use RuntimeException;

final readonly class TellShellJobHostBuilder
{
    public function __construct(
        private string $projectDirectory,
        private TellShellJobPolicy $policy,
        private CanApproveTellShellJobs $approval,
        private CanObserveTellShellJobs $observer,
    ) {}

    public function boot(): TellShellJobHost {
        $projectDirectory = realpath($this->projectDirectory);
        if ($projectDirectory === false || !is_dir($projectDirectory)) {
            throw new RuntimeException('The Tell shell-job host project directory does not exist.');
        }

        $events = new TellShellJobEventEmitter($this->observer);
        $events->emit('module.loading', 'shell.jobs', [
            'previous' => 'defined',
            'current' => 'loading',
        ]);
        $manager = new TellShellJobs(
            projectDirectory: $projectDirectory,
            policy: $this->policy,
            approval: $this->approval,
            events: $events,
        );
        $events->emit('module.active', 'shell.jobs', [
            'previous' => 'loading',
            'current' => 'active',
        ]);

        return new TellShellJobHost($manager, $events);
    }
}
