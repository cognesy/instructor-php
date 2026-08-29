<?php

declare(strict_types=1);

namespace Cognesy\Tell\Shell;

use Cognesy\Tell\Contracts\CanApproveTellShellJobs;
use Cognesy\Tell\Contracts\CanManageTellShellJobs;
use Cognesy\Tell\Contracts\CanObserveTellShellJobs;
use CordisPhp\Plugin\PluginDefinition;
use CordisPhp\Runtime\Context;
use CordisPhp\Runtime\FiberState;
use CordisPhp\Runtime\FiberStatusChange;
use CordisPhp\Runtime\Runtime;
use RuntimeException;
use Throwable;

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

        $runtime = new Runtime();
        $events = new TellShellJobEventEmitter($this->observer);
        $unsubscribe = $runtime->onStatus(static function (FiberStatusChange $change) use ($events): void {
            $events->emit('module.' . $change->current->value, $change->fiber->label(), [
                'previous' => $change->previous->value,
                'current' => $change->current->value,
            ], $change->current === FiberState::Failed ? 'failed' : null);
        });
        $manager = null;

        $fiber = $runtime->mount(
            PluginDefinition::fromClosure(function (Context $context) use (
                &$manager,
                $projectDirectory,
                $events,
            ) {
                $manager = new CordisTellShellJobs(
                    context: $context,
                    projectDirectory: $projectDirectory,
                    policy: $this->policy,
                    approval: $this->approval,
                    events: $events,
                );
                $context->provide(CanManageTellShellJobs::class, $manager);

                return static function () use ($manager): void {
                    $manager->dispose();
                };
            }),
            label: 'shell.jobs',
        );

        if (!$manager instanceof CordisTellShellJobs || $fiber->state() !== FiberState::Active) {
            $error = $fiber->error();
            try {
                $runtime->dispose();
            } catch (Throwable) {
                // The startup error remains the primary failure.
            } finally {
                $unsubscribe();
            }
            throw new RuntimeException('The Tell shell-job host could not be booted.', previous: $error);
        }

        return new TellShellJobHost($runtime, $manager, $unsubscribe);
    }
}
