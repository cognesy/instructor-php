<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class TextRenderer implements OutputRenderer
{
    public function __construct(
        private OutputInterface $stdout,
        private OutputInterface $stderr,
        private bool $verbose = false,
        private bool $quiet = false,
    ) {}

    #[Override]
    public function attach(AgentLoop $loop): void
    {
        (new EventProgress($this->stderr, $this->verbose, $this->quiet))->attach($loop);
    }

    #[Override]
    public function finish(AgentState $state, array $warnings = [], bool $transient = false): void
    {
        $verbosity = match ($this->quiet) {
            true => OutputInterface::VERBOSITY_QUIET,
            false => OutputInterface::VERBOSITY_NORMAL,
        };
        $this->stdout->writeln($state->finalResponse()->toString(), $verbosity);
        if ($transient) {
            $this->stderr->writeln('[tell] transient: no conversation or session state was persisted.', $verbosity);
        }
        foreach ($warnings as $warning) {
            $this->stderr->writeln('[tell] '.$warning, $verbosity);
        }
    }
}
