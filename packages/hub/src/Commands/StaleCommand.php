<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Contracts\CanExecuteExample;
use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StaleCommand extends Command
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
            ->setName('stale')
            ->setDescription('Run examples whose source files have been modified since last execution')
            ->addOption('threshold', 't', InputOption::VALUE_REQUIRED,
                'How old results can be (e.g. "1 hour", "1 day")', '1 day')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED,
                'Limit number of examples to run', 0)
            ->addOption('dry-run', null, InputOption::VALUE_NONE,
                'Show what would be executed without running');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $dryRun = $input->getOption('dry-run');

        // Find stale examples
        $staleExamples = [];
        $this->examples->forEachExample(function($example) use (&$staleExamples) {
            $status = $this->tracker->getStatus($example);

            // If no status exists, it's never been run - consider it stale
            if (!$status) {
                $staleExamples[] = [
                    'example' => $example,
                    'reason' => 'never executed',
                ];
                return true;
            }

            // Check if file has been modified since last execution
            if ($status->isStale()) {
                $staleExamples[] = [
                    'example' => $example,
                    'reason' => 'file modified',
                    'lastExecuted' => $status->lastExecuted,
                ];
            }

            return true;
        });

        if (empty($staleExamples)) {
            Cli::outln('');
            Cli::outln('No stale examples found. All examples are up to date.', [Color::GREEN]);
            Cli::outln('');
            return Command::SUCCESS;
        }

        // Apply limit
        if ($limit > 0) {
            $staleExamples = array_slice($staleExamples, 0, $limit);
        }

        Cli::outln('');
        Cli::outln('Found ' . count($staleExamples) . ' stale example(s)', [Color::YELLOW, Color::BOLD]);
        Cli::outln('');

        if ($dryRun) {
            $this->showDryRun($staleExamples);
            return Command::SUCCESS;
        }

        return $this->runStaleExamples($staleExamples);
    }

    private function showDryRun(array $staleExamples): void
    {
        Cli::outln('Would re-run the following stale examples:', [Color::YELLOW]);
        Cli::outln('');

        foreach ($staleExamples as $item) {
            $example = $item['example'];
            $reason = $item['reason'];
            $lastExecuted = $item['lastExecuted'] ?? null;

            Cli::out("  [{$example->index}] ", [Color::DARK_GRAY]);
            Cli::out("{$example->group}/{$example->name}", [Color::WHITE]);
            Cli::out(" ({$reason})", [Color::YELLOW]);

            if ($lastExecuted) {
                Cli::out(" - last run: " . $lastExecuted->format('M j, H:i'), [Color::DARK_GRAY]);
            }

            Cli::outln('');
        }
    }

    private function runStaleExamples(array $staleExamples): int
    {
        $this->runner->setTracker($this->tracker);

        $total = count($staleExamples);
        $current = 0;
        $success = 0;
        $errors = 0;

        Cli::outln('Running stale examples...', [Color::YELLOW]);
        Cli::outln('');

        foreach ($staleExamples as $item) {
            $example = $item['example'];
            $current++;

            Cli::out("({$current}/{$total}) ", [Color::DARK_GRAY]);
            Cli::out("{$example->group}/{$example->name}", [Color::WHITE]);
            Cli::out(" ... ", [Color::DARK_GRAY]);

            $startTime = microtime(true);
            $result = $this->runner->execute($example);
            $endTime = microtime(true);

            if ($result->isSuccessful()) {
                Cli::out("OK", [Color::GREEN]);
                $success++;
            } else {
                Cli::out("ERROR", [Color::RED]);
                $errors++;

                if ($result->error) {
                    Cli::outln('');
                    Cli::outln("    " . Cli::limit($result->error->message, 60), [Color::RED]);
                }
            }

            Cli::outln(" (" . round($endTime - $startTime, 2) . "s)", [Color::DARK_GRAY]);
        }

        Cli::outln('');
        Cli::outln('Results:', [Color::BOLD, Color::YELLOW]);
        Cli::outln("  Successful: {$success}", [Color::GREEN]);

        if ($errors > 0) {
            Cli::outln("  Errors:     {$errors}", [Color::RED]);
        }

        Cli::outln('');

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
