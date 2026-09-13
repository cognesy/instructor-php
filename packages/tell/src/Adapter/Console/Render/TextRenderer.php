<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Core\Observation\TellEventNormalizer;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellResult;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class TextRenderer implements OutputRenderer
{
    public function __construct(
        private OutputInterface $stdout,
        private OutputInterface $stderr,
        private bool $quiet = false,
    ) {}

    /**
     * Progress channels belong to the invocation, not to one output format, so
     * they are attached alongside this renderer rather than by it.
     */
    #[Override]
    public function attach(AgentLoop $loop, ?TellEventNormalizer $events = null): void {}

    #[Override]
    public function finish(TellResult $result): void {
        $verbosity = match ($this->quiet) {
            true => OutputInterface::VERBOSITY_QUIET,
            false => OutputInterface::VERBOSITY_NORMAL,
        };
        $answer = AgentResult::answer($result);
        if ($answer !== '') {
            $this->stdout->writeln($answer, $verbosity);
        }
        foreach (AgentResult::errorMessages($result) as $error) {
            $this->stderr->writeln('[tell] execution failed: ' . $error, $verbosity);
        }
        $terminal = AgentResult::terminalSummary($result);
        if ($terminal !== null) {
            $this->stderr->writeln($terminal, $verbosity);
        }
        if ($result->mode() === TellExecutionMode::Transient) {
            $this->stderr->writeln('[tell] transient: no conversation or session state was persisted.', $verbosity);
        }
        if ($result->branch() !== null) {
            $source = $result->branchSource() ?? 'current';
            $this->stderr->writeln("[tell] branch: {$result->branch()} ({$source}).", $verbosity);
        }
        foreach ($result->warnings() as $warning) {
            $this->stderr->writeln('[tell] ' . $warning, $verbosity);
        }
        foreach ($result->diagnostics() as $diagnostic) {
            $this->stderr->writeln("[tell] {$diagnostic->severity} {$diagnostic->code}: {$diagnostic->message}", $verbosity);
        }
    }
}
