<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Contracts\CanExecuteExample;
use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\ExecutionStatus;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\ExecutionTracker;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ErrorsCommand extends Command
{
    public function __construct(
        private CanExecuteExample $runner,
        private ExampleRepository $examples,
        private ExecutionTracker $tracker,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('errors')
            ->setDescription('Manage examples that failed in previous executions')
            ->addOption('list', null, InputOption::VALUE_NONE,
                'List failed examples with error details')
            ->addOption('run', null, InputOption::VALUE_NONE,
                'Re-run failed examples')
            ->addOption('clear', null, InputOption::VALUE_NONE,
                'Clear all error statuses')
            ->addOption('since', 's', InputOption::VALUE_REQUIRED,
                'Only errors since date (e.g. "yesterday", "1 hour ago")')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED,
                'Limit number of examples', 0)
            ->addOption('dry-run', null, InputOption::VALUE_NONE,
                'Show what would be executed without running');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $list = $input->getOption('list');
        $run = $input->getOption('run');
        $clear = $input->getOption('clear');

        if (!$list && !$run && !$clear) {
            return $this->showHelp();
        }

        if ($clear) {
            return $this->clearErrors();
        }

        $errorStatuses = $this->getErrorStatuses($input);

        if ($errorStatuses === null) {
            return Command::FAILURE;
        }

        if (empty($errorStatuses)) {
            Cli::outln('');
            Cli::outln('No failed examples found.', [Color::GREEN]);
            Cli::outln('');
            return Command::SUCCESS;
        }

        if ($list) {
            Cli::outln('');
            Cli::outln('Found ' . count($errorStatuses) . ' failed example(s)', [Color::YELLOW, Color::BOLD]);
            Cli::outln('');
            $this->showErrorDetails($errorStatuses);
            return Command::SUCCESS;
        }

        // --run
        if ($input->getOption('dry-run')) {
            Cli::outln('');
            Cli::outln('Found ' . count($errorStatuses) . ' failed example(s)', [Color::YELLOW, Color::BOLD]);
            Cli::outln('');
            $this->showDryRun($errorStatuses);
            return Command::SUCCESS;
        }

        return $this->reRunErrors($errorStatuses);
    }

    private function showHelp(): int
    {
        Cli::outln('');
        Cli::outln('Manage failed examples:', [Color::YELLOW, Color::BOLD]);
        Cli::outln('');
        Cli::outln('  composer hub errors --list              List failed examples with error details', [Color::WHITE]);
        Cli::outln('  composer hub errors --run               Re-run failed examples', [Color::WHITE]);
        Cli::outln('  composer hub errors --run --dry-run     Show what would be re-run', [Color::WHITE]);
        Cli::outln('  composer hub errors --clear             Clear all error statuses', [Color::WHITE]);
        Cli::outln('');
        Cli::outln('Options:', [Color::YELLOW]);
        Cli::outln('  --since, -s    Filter by date (e.g. "yesterday", "1 hour ago")', [Color::DARK_GRAY]);
        Cli::outln('  --limit, -l    Limit number of examples', [Color::DARK_GRAY]);
        Cli::outln('');
        return Command::SUCCESS;
    }

    private function getErrorStatuses(InputInterface $input): ?array
    {
        $since = $input->getOption('since');
        $limit = (int) $input->getOption('limit');

        $errorStatuses = array_filter(
            $this->tracker->getAllStatuses(),
            fn($s) => $s->status === ExecutionStatus::ERROR
        );

        if ($since) {
            try {
                $sinceTime = new \DateTimeImmutable($since);
                $errorStatuses = array_filter(
                    $errorStatuses,
                    fn($s) => $s->lastExecuted !== null && $s->lastExecuted >= $sinceTime
                );
            } catch (\Exception $e) {
                Cli::outln("Invalid date format: {$since}", [Color::RED]);
                return null;
            }
        }

        if ($limit > 0) {
            $errorStatuses = array_slice($errorStatuses, 0, $limit);
        }

        return $errorStatuses;
    }

    private function clearErrors(): int
    {
        $removed = $this->tracker->removeErrorStatuses();

        Cli::outln('');
        if ($removed > 0) {
            Cli::outln("Cleared {$removed} error status(es).", [Color::GREEN]);
        } else {
            Cli::outln('No error statuses to clear.', [Color::YELLOW]);
        }
        Cli::outln('');

        return Command::SUCCESS;
    }

    private function showErrorDetails(array $errorStatuses): void
    {
        foreach ($errorStatuses as $status) {
            Cli::outln("[{$status->index}] {$status->group}/{$status->name}", [Color::WHITE, Color::BOLD]);

            $lastError = $status->lastError();
            if ($lastError) {
                Cli::outln("  Type: {$lastError->type}", [Color::DARK_GRAY]);
                Cli::outln("  Message: " . Cli::limit($lastError->message, 70), [Color::RED]);
                Cli::outln("  When: " . ($status->lastExecuted?->format('Y-m-d H:i:s') ?? 'Unknown'), [Color::DARK_GRAY]);
            }
            Cli::outln('');
        }
    }

    private function showDryRun(array $errorStatuses): void
    {
        Cli::outln('Would re-run the following examples:', [Color::YELLOW]);
        Cli::outln('');

        foreach ($errorStatuses as $status) {
            $lastError = $status->lastError();
            Cli::out("  [{$status->index}] ", [Color::DARK_GRAY]);
            Cli::out("{$status->group}/{$status->name}", [Color::WHITE]);
            if ($lastError) {
                Cli::out(" - " . Cli::limit($lastError->type, 20), [Color::RED]);
            }
            Cli::outln('');
        }
    }

    private function reRunErrors(array $errorStatuses): int
    {
        $this->runner->setTracker($this->tracker);

        $total = count($errorStatuses);
        $current = 0;
        $fixed = 0;
        $stillFailing = 0;

        Cli::outln('');
        Cli::outln("Re-running {$total} failed example(s)...", [Color::YELLOW, Color::BOLD]);
        Cli::outln('');

        $exampleMap = [];
        $this->examples->forEachExample(function($example) use (&$exampleMap) {
            $exampleMap[$example->index] = $example;
            return true;
        });

        foreach ($errorStatuses as $status) {
            $current++;

            if (!isset($exampleMap[$status->index])) {
                Cli::outln("  [{$status->index}] Example not found, skipping", [Color::DARK_GRAY]);
                continue;
            }

            $example = $exampleMap[$status->index];

            Cli::out("({$current}/{$total}) ", [Color::DARK_GRAY]);
            Cli::out("{$example->group}/{$example->name}", [Color::WHITE]);
            Cli::out(" ... ", [Color::DARK_GRAY]);

            $startTime = microtime(true);
            $result = $this->runner->execute($example);
            $endTime = microtime(true);

            if ($result->isSuccessful()) {
                Cli::out("FIXED", [Color::GREEN, Color::BOLD]);
                $fixed++;
            } else {
                Cli::out("STILL FAILING", [Color::RED]);
                $stillFailing++;
            }

            Cli::outln(" (" . round($endTime - $startTime, 2) . "s)", [Color::DARK_GRAY]);
        }

        Cli::outln('');
        Cli::outln('Results:', [Color::BOLD, Color::YELLOW]);
        Cli::outln("  Fixed:         {$fixed}", [Color::GREEN]);
        Cli::outln("  Still failing: {$stillFailing}", [Color::RED]);
        Cli::outln('');

        return $stillFailing > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
