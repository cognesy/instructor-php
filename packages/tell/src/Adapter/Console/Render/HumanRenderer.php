<?php

declare(strict_types=1);

namespace Cognesy\Tell\Adapter\Console\Render;

use Cognesy\Agents\AgentLoop;
use Cognesy\Tell\Core\Observation\TellEventNormalizer;
use Cognesy\Tell\Data\TellExecutionMode;
use Cognesy\Tell\Data\TellResult;
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
            // OUTPUT_RAW: the rendered answer already carries its own escape
            // sequences, and model text may contain angle brackets that the
            // console formatter would otherwise read as its own markup.
            $this->stdout->write(
                $this->rendered($answer) . "\n",
                false,
                OutputInterface::OUTPUT_RAW | $verbosity,
            );
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

    /**
     * Markdown rendering is for a terminal only. A redirected or piped stream
     * carries no colours, so it keeps the answer as the model wrote it and
     * stays usable as input to something else.
     */
    private function rendered(string $answer): string {
        if (!$this->stdout->isDecorated()) {
            return $answer;
        }

        return rtrim((new CliMarkdown())->render($answer), "\n");
    }
}
