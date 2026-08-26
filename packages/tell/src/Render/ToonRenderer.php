<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Tell\Observability\TellEventNormalizer;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ToonRenderer implements OutputRenderer
{
    private StructuredOutput $output;

    private EventProgress $progress;

    public function __construct(
        OutputInterface $output,
        OutputInterface $stderr,
        bool $verbose = false,
        bool $quiet = false,
    ) {
        $this->output = new StructuredOutput($output);
        $this->progress = new EventProgress($stderr, $verbose, $quiet);
    }

    #[Override]
    public function attach(AgentLoop $loop, ?TellEventNormalizer $events = null): void
    {
        $this->progress->attach($loop, $events);
    }

    #[Override]
    public function finish(AgentState $state, array $warnings = [], bool $transient = false, ?array $branch = null, array $diagnostics = []): void
    {
        $this->output->write(AgentResult::fromState($state, $warnings, $transient, $branch, $diagnostics));
    }
}
