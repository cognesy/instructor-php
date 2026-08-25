<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Events\Event;
use JsonException;
use Override;
use ReflectionClass;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class EventsRenderer implements OutputRenderer
{
    public function __construct(private OutputInterface $stdout) {}

    #[Override]
    public function attach(AgentLoop $loop): void
    {
        $loop->wiretap(function (object $event): void {
            $this->stdout->writeln($this->encode($event));
        });
    }

    #[Override]
    public function finish(AgentState $state, array $warnings = [], bool $transient = false): void
    {
        if ($transient) {
            $this->stdout->writeln(json_encode([
                'event' => 'TellTransientExecution',
                'data' => ['durable' => false],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        }
        foreach ($warnings as $warning) {
            $this->stdout->writeln(json_encode([
                'event' => 'WorkspaceSessionWarning',
                'data' => ['message' => $warning],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        }
    }

    /** @throws JsonException */
    private function encode(object $event): string
    {
        $payload = match (true) {
            $event instanceof Event => $event->data,
            default => get_object_vars($event),
        };

        return json_encode([
            'event' => (new ReflectionClass($event))->getShortName(),
            'data' => $payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
