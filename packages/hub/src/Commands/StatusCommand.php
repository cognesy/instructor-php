<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\ExecutionStatus;
use Cognesy\InstructorHub\Data\ExecutionSummary;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class StatusCommand extends Command
{
    public function __construct(
        private CanTrackExecution $tracker,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Show execution status summary')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE,
                'Show detailed status for each example')
            ->addOption('errors-only', 'e', InputOption::VALUE_NONE,
                'Show only examples with errors')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED,
                'Output format: table|json|csv', 'table');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $detailed = (bool) $input->getOption('detailed');
        $errorsOnly = (bool) $input->getOption('errors-only');
        $format = (string) $input->getOption('format');



        try {
            $summary = $this->tracker->getSummary();
        } catch (\Throwable $e) {
            Cli::outln('Error retrieving status: ' . $e->getMessage(), [Color::RED]);
            return Command::FAILURE;
        }

        return match($format) {
            'json' => $this->outputJson($summary, $detailed, $errorsOnly),
            'csv' => $this->outputCsv($detailed, $errorsOnly, $output),
            default => $this->outputTable($summary, $detailed, $errorsOnly, $output),
        };
    }

    private function outputTable(ExecutionSummary $summary, bool $detailed, bool $errorsOnly, OutputInterface $output): int
    {
        // Show summary
        Cli::outln('');
        Cli::outln('Execution Status Summary', [Color::BOLD, Color::YELLOW]);
        Cli::outln('========================', [Color::YELLOW]);
        Cli::outln('');

        Cli::out('Total Examples: ', [Color::DARK_GRAY]);
        Cli::outln((string)$summary->totalExamples, [Color::WHITE, Color::BOLD]);

        Cli::out('Executed:       ', [Color::DARK_GRAY]);
        Cli::outln((string)$summary->executed, [Color::WHITE]);

        Cli::out('Completed:      ', [Color::DARK_GRAY]);
        Cli::outln((string)$summary->completed, [Color::GREEN]);

        if ($summary->errors > 0) {
            Cli::out('Errors:         ', [Color::DARK_GRAY]);
            Cli::outln((string)$summary->errors, [Color::RED]);
        }

        if ($summary->interrupted > 0) {
            Cli::out('Interrupted:    ', [Color::DARK_GRAY]);
            Cli::outln((string)$summary->interrupted, [Color::YELLOW]);
        }

        if ($summary->skipped > 0) {
            Cli::out('Skipped:        ', [Color::DARK_GRAY]);
            Cli::outln((string)$summary->skipped, [Color::DARK_GRAY]);
        }

        Cli::outln('');
        Cli::out('Success Rate:   ', [Color::DARK_GRAY]);
        $rateColor = $summary->successRate() >= 90 ? Color::GREEN : ($summary->successRate() >= 70 ? Color::YELLOW : Color::RED);
        Cli::outln($summary->successRate() . '%', [$rateColor, Color::BOLD]);

        Cli::out('Average Time:   ', [Color::DARK_GRAY]);
        Cli::outln(round($summary->averageTime, 2) . 's', [Color::WHITE]);

        Cli::out('Total Time:     ', [Color::DARK_GRAY]);
        Cli::outln(round($summary->totalTime, 2) . 's', [Color::WHITE]);

        if ($summary->slowestExample) {
            Cli::out('Slowest:        ', [Color::DARK_GRAY]);
            $path = $summary->slowestExample['path'] ?? '';
            Cli::outln($summary->slowestExample['name'] . ' (' . round($summary->slowestExample['time'], 2) . 's) - ' . $path, [Color::YELLOW]);
        }

        if ($summary->fastestExample) {
            Cli::out('Fastest:        ', [Color::DARK_GRAY]);
            $path = $summary->fastestExample['path'] ?? '';
            Cli::outln($summary->fastestExample['name'] . ' (' . round($summary->fastestExample['time'], 2) . 's) - ' . $path, [Color::GREEN]);
        }

        if ($detailed) {
            $this->showDetailedTable($errorsOnly, $output);
        }

        Cli::outln('');

        return Command::SUCCESS;
    }

    private function showDetailedTable(bool $errorsOnly, OutputInterface $output): void
    {
        $statuses = $this->tracker->getAllStatuses();

        if ($errorsOnly) {
            $statuses = array_filter($statuses, fn($s) => $s->status === ExecutionStatus::ERROR);
        }

        if (empty($statuses)) {
            Cli::outln('');
            Cli::outln('No examples to display.', [Color::DARK_GRAY]);
            return;
        }

        Cli::outln('');
        Cli::outln('Detailed Status:', [Color::BOLD, Color::YELLOW]);
        Cli::outln('');

        $table = new Table($output);
        $table->setHeaders(['Index', 'Name', 'Group', 'Status', 'Time (s)', 'Last Run', 'Attempts']);

        foreach ($statuses as $status) {
            $statusColor = match($status->status) {
                ExecutionStatus::COMPLETED => 'green',
                ExecutionStatus::ERROR => 'red',
                ExecutionStatus::INTERRUPTED => 'yellow',
                ExecutionStatus::RUNNING => 'cyan',
                ExecutionStatus::STALE => 'magenta',
                default => 'white',
            };

            $table->addRow([
                $status->index + 1,
                Cli::limit($status->name, 30),
                Cli::limit($status->group, 20),
                "<fg={$statusColor}>" . $status->status->value . "</>",
                $status->executionTime > 0 ? round($status->executionTime, 2) : '-',
                $status->lastExecuted?->format('M j, H:i') ?? 'Never',
                $status->attempts,
            ]);
        }

        $table->render();
    }

    private function outputJson(ExecutionSummary $summary, bool $detailed, bool $errorsOnly): int
    {
        $data = [
            'summary' => $summary->toArray(),
        ];

        if ($detailed) {
            $statuses = $this->tracker->getAllStatuses();

            if ($errorsOnly) {
                $statuses = array_filter($statuses, fn($s) => $s->status === ExecutionStatus::ERROR);
            }

            $data['examples'] = array_map(fn($s) => $s->toArray(), $statuses);
        }

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        echo "\n";

        return Command::SUCCESS;
    }

    private function outputCsv(bool $detailed, bool $errorsOnly, OutputInterface $output): int
    {
        $statuses = $this->tracker->getAllStatuses();

        if ($errorsOnly) {
            $statuses = array_filter($statuses, fn($s) => $s->status === ExecutionStatus::ERROR);
        }

        // Header
        echo "index,name,group,status,execution_time,last_executed,attempts,exit_code\n";

        foreach ($statuses as $status) {
            echo implode(',', [
                $status->index + 1,
                '"' . str_replace('"', '""', $status->name) . '"',
                '"' . str_replace('"', '""', $status->group) . '"',
                $status->status->value,
                round($status->executionTime, 4),
                $status->lastExecuted?->format('c') ?? '',
                $status->attempts,
                $status->exitCode,
            ]) . "\n";
        }

        return Command::SUCCESS;
    }
}
