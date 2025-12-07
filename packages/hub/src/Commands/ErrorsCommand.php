<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Contracts\CanExecuteExample;
use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\ExecutionStatus;
use Cognesy\InstructorHub\Services\ExampleRepository;
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
        private CanTrackExecution $tracker,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('errors')
            ->setDescription('Re-run examples that failed in previous executions')
            ->addOption('since', 's', InputOption::VALUE_REQUIRED,
                'Only errors since date (e.g. "yesterday", "1 hour ago")')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED,
                'Limit number of examples to run', 0)
            ->addOption('dry-run', null, InputOption::VALUE_NONE,
                'Show what would be executed without running')
            ->addOption('show-errors', null, InputOption::VALUE_NONE,
                'Show error details without re-running');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $since = $input->getOption('since');
        $limit = (int) $input->getOption('limit');
        $dryRun = $input->getOption('dry-run');
        $showErrors = $input->getOption('show-errors');

        // Get all statuses with errors
        $errorStatuses = array_filter(
            $this->tracker->getAllStatuses(),
            fn($s) => $s->status === ExecutionStatus::ERROR
        );

        // Filter by time if specified
        if ($since) {
            try {
                $sinceTime = new \DateTimeImmutable($since);
                $errorStatuses = array_filter(
                    $errorStatuses,
                    fn($s) => $s->lastExecuted !== null && $s->lastExecuted >= $sinceTime
                );
            } catch (\Exception $e) {
                Cli::outln("Invalid date format: {$since}", [Color::RED]);
                return Command::FAILURE;
            }
        }

        if (empty($errorStatuses)) {
            Cli::outln('');
            Cli::outln('No failed examples found.', [Color::GREEN]);
            Cli::outln('');
            return Command::SUCCESS;
        }

        // Apply limit
        if ($limit > 0) {
            $errorStatuses = array_slice($errorStatuses, 0, $limit);
        }

        Cli::outln('');
        Cli::outln('Found ' . count($errorStatuses) . ' failed example(s)', [Color::YELLOW, Color::BOLD]);
        Cli::outln('');

        if ($showErrors) {
            $this->showErrorDetails($errorStatuses);
            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->showDryRun($errorStatuses);
            return Command::SUCCESS;
        }

        return $this->reRunErrors($errorStatuses);
    }

    private function showErrorDetails(array $errorStatuses): void
    {
        foreach ($errorStatuses as $status) {
            Cli::outln("[" . ($status->index + 1) . "] {$status->group}/{$status->name}", [Color::WHITE, Color::BOLD]);

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

        Cli::outln('Re-running failed examples...', [Color::YELLOW]);
        Cli::outln('');

        // Map status indices to examples
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
