<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Tell\Observability\TellEventNormalizer;
use Cognesy\Tell\TellExecutionMode;
use JsonException;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

final class EventsRenderer implements OutputRenderer
{
    private ?TellEventNormalizer $events = null;

    private bool $terminal = false;

    public function __construct(private OutputInterface $stdout) {}

    #[Override]
    public function attach(AgentLoop $loop, ?TellEventNormalizer $events = null): void
    {
        $this->events = $events ?? new TellEventNormalizer;
        $loop->wiretap(function (object $event): void {
            $envelope = $this->normalizer()->normalize($event);
            if ($envelope['terminal'] !== null && $this->terminal) {
                return;
            }
            $this->terminal = $envelope['terminal'] !== null;
            $this->stdout->writeln($this->encode($envelope));
        });
    }

    #[Override]
    public function finish(AgentState $state, array $warnings = [], TellExecutionMode $mode = TellExecutionMode::Stateless, ?array $branch = null, array $diagnostics = []): void
    {
        if (! $this->terminal) {
            $this->stdout->writeln($this->encode($this->normalizer()->terminal(
                $state->status()->value,
                ['steps' => $state->stepCount()],
            )));
            $this->terminal = true;
        }
    }

    /** @throws JsonException */
    private function encode(array $envelope): string
    {
        return json_encode($envelope, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function normalizer(): TellEventNormalizer
    {
        return $this->events ??= new TellEventNormalizer;
    }
}
