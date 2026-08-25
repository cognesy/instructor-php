<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Observability\TellEventNormalizer;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class EventProgress
{
    public function __construct(
        private OutputInterface $stderr,
        private bool $verbose = false,
        private bool $quiet = false,
    ) {}

    public function attach(AgentLoop $loop, ?TellEventNormalizer $events = null): void
    {
        if ($this->quiet) {
            return;
        }
        $normalizer = $events ?? new TellEventNormalizer;
        $loop->wiretap(function (object $event) use ($normalizer): void {
            $envelope = $normalizer->normalize($event);
            $metadata = $envelope['metadata'];
            match ($envelope['kind']) {
                'inference.started' => $this->stderr->writeln('[inference.start] step='.($metadata['step'] ?? 0)),
                'step.started' => $this->verbose ? $this->stderr->writeln('[step.start] step='.($metadata['step'] ?? 0)) : null,
                'step.completed' => $this->verbose ? $this->stderr->writeln('[step.complete] step='.($metadata['step'] ?? 0)) : null,
                'tool.started' => $this->verbose ? $this->stderr->writeln('[tool.start] name='.($metadata['tool'] ?? 'unknown')) : null,
                'tool.completed' => $this->verbose ? $this->stderr->writeln(
                    '[tool.complete] name='.($metadata['tool'] ?? 'unknown').' status='.(($metadata['success'] ?? false) ? 'ok' : 'failed'),
                ) : null,
                default => null,
            };
        });
    }
}
