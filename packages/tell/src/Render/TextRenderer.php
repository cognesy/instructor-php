<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Tell\Observability\TellEventNormalizer;
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
    public function attach(AgentLoop $loop, ?TellEventNormalizer $events = null): void
    {
        (new EventProgress($this->stderr, $this->verbose, $this->quiet))->attach($loop, $events);
    }

    #[Override]
    public function finish(AgentState $state, array $warnings = [], bool $transient = false, ?array $branch = null): void
    {
        $verbosity = match ($this->quiet) {
            true => OutputInterface::VERBOSITY_QUIET,
            false => OutputInterface::VERBOSITY_NORMAL,
        };
        $answer = AgentResult::answer($state);
        if ($answer !== '') {
            $this->stdout->writeln($answer, $verbosity);
        }
        if ($state->stopSignal() !== null) {
            $this->stderr->writeln('[tell] execution stopped: '.$state->stopSignal()->toString(), $verbosity);
        }
        if ($transient) {
            $this->stderr->writeln('[tell] transient: no conversation or session state was persisted.', $verbosity);
        }
        if ($branch !== null) {
            $this->stderr->writeln("[tell] branch: {$branch['name']} ({$branch['source']}).", $verbosity);
        }
        foreach ($warnings as $warning) {
            $this->stderr->writeln('[tell] '.$warning, $verbosity);
        }
    }
}
