<?php

declare(strict_types=1);

namespace Cognesy\Tell\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Tell\Observability\TellEventNormalizer;
use Cognesy\Tell\TellExecutionMode;
use Cognesy\Utils\Cli\CliMarkdown;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Text output for a reader at a terminal. Agent answers are Markdown, so this
 * renders them rather than printing the source; everything that is not the
 * answer stays on stderr exactly as the plain text renderer puts it there.
 */
final readonly class HumanRenderer implements OutputRenderer
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
    public function finish(AgentState $state, array $warnings = [], TellExecutionMode $mode = TellExecutionMode::Stateless, ?array $branch = null, array $diagnostics = []): void
    {
        $verbosity = match ($this->quiet) {
            true => OutputInterface::VERBOSITY_QUIET,
            false => OutputInterface::VERBOSITY_NORMAL,
        };
        $answer = AgentResult::answer($state);
        if ($answer !== '') {
            // OUTPUT_RAW: the rendered answer already carries its own escape
            // sequences, and model text may contain angle brackets that the
            // console formatter would otherwise read as its own markup.
            $this->stdout->write(
                $this->rendered($answer)."\n",
                false,
                OutputInterface::OUTPUT_RAW | $verbosity,
            );
        }
        if ($state->stopSignal() !== null) {
            $this->stderr->writeln('[tell] execution stopped: '.$state->stopSignal()->toString(), $verbosity);
        }
        if ($mode === TellExecutionMode::Transient) {
            $this->stderr->writeln('[tell] transient: no conversation or session state was persisted.', $verbosity);
        }
        if ($branch !== null) {
            $this->stderr->writeln("[tell] branch: {$branch['name']} ({$branch['source']}).", $verbosity);
        }
        foreach ($warnings as $warning) {
            $this->stderr->writeln('[tell] '.$warning, $verbosity);
        }
        foreach ($diagnostics as $diagnostic) {
            $this->stderr->writeln("[tell] {$diagnostic['severity']} {$diagnostic['code']}: {$diagnostic['message']}", $verbosity);
        }
    }

    /**
     * Markdown rendering is for a terminal only. A redirected or piped stream
     * carries no colours, so it keeps the answer as the model wrote it and
     * stays usable as input to something else.
     */
    private function rendered(string $answer): string
    {
        if (! $this->stdout->isDecorated()) {
            return $answer;
        }

        return rtrim((new CliMarkdown)->render($answer), "\n");
    }
}
