<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Contracts\CanExecuteExample;
use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\ExecutionFilter;
use Cognesy\InstructorHub\Data\FilterMode;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnhancedRunAllExamples extends Command
{
    public function __construct(
        private CanExecuteExample $runner,
        private ExampleRepository $examples,
        private CanTrackExecution $tracker,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('all')
            ->setDescription('Run all examples with enhanced tracking')
            ->addArgument('index', InputArgument::OPTIONAL, 'Starting index (optional)')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED,
                'Filter mode: all|errors|stale|pending|not-completed', 'all')
            ->addOption('force', null, InputOption::VALUE_NONE,
                'Force execution even if recently run')
            ->addOption('dry-run', null, InputOption::VALUE_NONE,
                'Show what would be executed without running')
            ->addOption('stop-on-error', null, InputOption::VALUE_NONE,
                'Stop execution on first error')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED,
                'Limit number of examples to run', 0);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startIndex = $input->getArgument('index') ? (int) $input->getArgument('index') : 0;
        $filterMode = FilterMode::fromString($input->getOption('filter'));
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $stopOnError = $input->getOption('stop-on-error');
        $limit = (int) $input->getOption('limit');

        $this->runner->setTracker($this->tracker);

        $filter = ExecutionFilter::fromMode($filterMode);
        $examples = $this->getFilteredExamples($filter, $force, $startIndex);

        if ($limit > 0) {
            $examples = array_slice($examples, 0, $limit);
        }

        if (empty($examples)) {
            Cli::outln('');
            Cli::outln('No examples match the specified criteria.', [Color::YELLOW]);
            Cli::outln('');
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->showDryRun($examples, $filter);
            return Command::SUCCESS;
        }

        return $this->executeExamples($examples, $stopOnError);
    }

    private function excludeUnrunnable(array $examples): array
    {
        $replay = $this->isReplayMode();

        $kept = [];
        $broken = [];
        $noReplay = [];
        foreach ($examples as $example) {
            if ($this->hasTag($example, 'broken')) {
                $broken[] = $example->docName;   // known-failing: never run, in any mode
            } elseif ($replay && $this->hasTag($example, 'no-replay')) {
                $noReplay[] = $example->docName; // cannot be recorded: skip the replay lane
            } else {
                $kept[] = $example;
            }
        }

        if ($broken !== []) {
            Cli::outln(
                sprintf('Skipping %d broken example(s): %s', count($broken), implode(', ', $broken)),
                [Color::DARK_GRAY],
            );
        }
        if ($noReplay !== []) {
            Cli::outln(
                sprintf('Replay: skipping %d no-replay example(s): %s', count($noReplay), implode(', ', $noReplay)),
                [Color::DARK_GRAY],
            );
        }

        return $kept;
    }

    private function isReplayMode(): bool
    {
        return getenv('INSTRUCTOR_EXAMPLES_HTTP') === 'replay';
    }

    private function hasTag(\Cognesy\InstructorHub\Data\Example $example, string $tag): bool
    {
        return in_array($tag, array_map('strtolower', $example->tags), true);
    }

    private function getFilteredExamples(ExecutionFilter $filter, bool $force, int $startIndex): array
    {
        $allExamples = [];
        $index = 1;

        $this->examples->forEachExample(function($example) use (&$allExamples, &$index, $startIndex) {
            if ($index >= $startIndex && !$example->skip) {
                $allExamples[] = $example;
            }
            $index++;
            return true;
        });

        // Drop examples that must not run: `broken` (known-failing, always) and, in
        // replay mode, `no-replay` (explicit-client / telemetry / sandbox). Logged,
        // never silent — the skipped set is a change-impact signal, not hidden loss.
        $allExamples = $this->excludeUnrunnable($allExamples);

        // If force is specified, return all examples (no filtering)
        if ($force) {
            return $allExamples;
        }

        // If filter is ALL, return all examples
        if ($filter->mode === FilterMode::ALL) {
            return $allExamples;
        }

        // Apply filter based on status
        $filtered = [];
        foreach ($allExamples as $example) {
            $status = $this->tracker->getStatus($example);

            // If no status exists and we're looking for pending/stale, include it
            if (!$status) {
                if (in_array($filter->mode, [FilterMode::PENDING_ONLY, FilterMode::STALE_ONLY, FilterMode::NOT_COMPLETED], true)) {
                    $filtered[] = $example;
                }
                continue;
            }

            if ($filter->shouldExecute($status)) {
                $filtered[] = $example;
            }
        }

        return $filtered;
    }

    private function showDryRun(array $examples, ExecutionFilter $filter): void
    {
        Cli::outln('');
        Cli::outln('Would execute ' . count($examples) . ' examples (' . $filter->getDescription() . '):', [Color::YELLOW]);
        Cli::outln('');

        foreach ($examples as $example) {
            $idTag = !empty($example->id) ? "[x{$example->id}] " : '';
            Cli::out("  [{$example->index}] ", [Color::DARK_GRAY]);
            Cli::out($idTag, [Color::CYAN]);
            Cli::outln("{$example->group}/{$example->name}", [Color::WHITE]);
        }
        Cli::outln('');
    }

    private function executeExamples(array $examples, bool $stopOnError): int
    {
        $total = count($examples);
        $current = 0;
        $success = 0;
        $errors = 0;
        $flaky = 0;

        Cli::outln('');
        Cli::outln("Executing {$total} examples...", [Color::BOLD, Color::YELLOW]);
        Cli::outln('');

        $overallStartTime = microtime(true);

        foreach ($examples as $example) {
            $current++;

            // Format: [1]   Basic                          > running ... >    OK    (  1.25 sec )
            $indexStr = "[{$current}]";
            $nameStr = $example->name;

            // Left-align index and name with proper padding
            Cli::out(str_pad($indexStr, 6, ' ', STR_PAD_RIGHT), [Color::DARK_GRAY]);
            Cli::out(str_pad($nameStr, 32, ' ', STR_PAD_RIGHT), [Color::WHITE]);
            Cli::out("> running ... > ", [Color::DARK_GRAY]);

            $startTime = microtime(true);
            $result = $this->runner->execute($example);
            $endTime = microtime(true);

            // A `flaky` example fails only from live LLM non-determinism, so a live
            // failure is tolerated (warn, don't fail the gate). Under replay there is
            // no non-determinism, so a flaky failure there IS real and counts.
            $flakyTolerated = $this->hasTag($example, 'flaky') && !$this->isReplayMode();

            if ($result->isSuccessful()) {
                Cli::out(str_pad("OK", 8, ' ', STR_PAD_BOTH), [Color::GREEN, Color::BOLD]);
                $success++;
            } elseif ($flakyTolerated) {
                Cli::out(str_pad("FLAKY", 8, ' ', STR_PAD_BOTH), [Color::DARK_YELLOW, Color::BOLD]);
                $flaky++;

                if ($result->error) {
                    Cli::outln('');
                    Cli::outln("    (tolerated) " . Cli::limit($result->error->message, 60), [Color::DARK_GRAY]);
                }
            } elseif ($result->error?->isAssertion()) {
                Cli::out(str_pad("ASSERT", 8, ' ', STR_PAD_BOTH), [Color::YELLOW, Color::BOLD]);
                $errors++;

                Cli::outln('');
                Cli::outln("    " . Cli::limit($result->error->message, 70), [Color::YELLOW]);

                if ($stopOnError) {
                    Cli::outln('');
                    Cli::outln('Stopping on first error as requested.', [Color::YELLOW]);
                    break;
                }
            } else {
                Cli::out(str_pad("ERROR", 8, ' ', STR_PAD_BOTH), [Color::RED, Color::BOLD]);
                $errors++;

                if ($result->error) {
                    Cli::outln('');
                    Cli::outln("    " . Cli::limit($result->error->message, 70), [Color::RED]);
                }

                if ($stopOnError) {
                    Cli::outln('');
                    Cli::outln('Stopping on first error as requested.', [Color::YELLOW]);
                    break;
                }
            }

            $timingStr = sprintf("(  %.2f sec )", $endTime - $startTime);
            Cli::outln($timingStr, [Color::DARK_GRAY]);

            // Check for interruption
            if (method_exists($this->runner, 'isInterrupted') && $this->runner->isInterrupted()) {
                Cli::outln('');
                Cli::outln('Execution interrupted by user.', [Color::YELLOW]);
                break;
            }
        }

        $overallEndTime = microtime(true);
        $this->displaySummary($current, $success, $errors, $flaky, $overallEndTime - $overallStartTime);

        // Gate on real errors only; tolerated flaky failures do not fail the run.
        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function displaySummary(int $executed, int $success, int $errors, int $flaky, float $totalTime): void
    {
        Cli::outln('');
        Cli::outln('Execution Summary:', [Color::BOLD, Color::YELLOW]);
        Cli::outln("  Executed:   {$executed}", [Color::WHITE]);
        Cli::outln("  Successful: {$success}", [Color::GREEN]);

        if ($flaky > 0) {
            Cli::outln("  Flaky:      {$flaky} (tolerated)", [Color::DARK_YELLOW]);
        }

        if ($errors > 0) {
            Cli::outln("  Errors:     {$errors}", [Color::RED]);
        }

        Cli::outln("  Total time: " . round($totalTime, 2) . "s", [Color::DARK_GRAY]);

        $summary = $this->tracker->getSummary();
        if ($summary->averageTime > 0) {
            Cli::outln("  Avg time:   " . round($summary->averageTime, 2) . "s", [Color::DARK_GRAY]);
        }

        Cli::outln('');
    }
}
