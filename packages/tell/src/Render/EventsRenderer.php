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
    public function finish(AgentState $state): void {}

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
