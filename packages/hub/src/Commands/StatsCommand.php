<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\ExecutionStatus;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class StatsCommand extends Command
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
            ->setName('stats')
            ->setDescription('Show execution statistics and performance metrics')
            ->addOption('since', 's', InputOption::VALUE_REQUIRED,
                'Statistics since date (e.g. "yesterday", "1 week ago")')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED,
                'Output format: table|json', 'table')
            ->addOption('slowest', null, InputOption::VALUE_REQUIRED,
                'Show N slowest examples', 10)
            ->addOption('fastest', null, InputOption::VALUE_REQUIRED,
                'Show N fastest examples', 0);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $since = $input->getOption('since');
        $format = $input->getOption('format');
        $slowestCount = (int) $input->getOption('slowest');
        $fastestCount = (int) $input->getOption('fastest');

        $statuses = $this->tracker->getAllStatuses();

        // Filter by time if specified
        if ($since) {
            try {
                $sinceTime = new \DateTimeImmutable($since);
                $statuses = array_filter(
                    $statuses,
                    fn($s) => $s->lastExecuted !== null && $s->lastExecuted >= $sinceTime
                );
            } catch (\Exception $e) {
                Cli::outln("Invalid date format: {$since}", [Color::RED]);
                return Command::FAILURE;
            }
        }

        if (empty($statuses)) {
            Cli::outln('');
            Cli::outln('No execution data available.', [Color::YELLOW]);
            Cli::outln('');
            return Command::SUCCESS;
        }

        if ($format === 'json') {
            return $this->outputJson($statuses, $slowestCount, $fastestCount);
        }

        return $this->outputTable($statuses, $slowestCount, $fastestCount, $output);
    }

    private function outputTable(array $statuses, int $slowestCount, int $fastestCount, OutputInterface $output): int
    {
        $stats = $this->calculateStats($statuses);

        Cli::outln('');
        Cli::outln('Execution Statistics', [Color::BOLD, Color::YELLOW]);
        Cli::outln('====================', [Color::YELLOW]);
        Cli::outln('');

        // Overview
        Cli::outln('Overview:', [Color::BOLD]);
        Cli::out('  Total Executed:    ', [Color::DARK_GRAY]);
        Cli::outln((string)$stats['total'], [Color::WHITE, Color::BOLD]);

        Cli::out('  Success Rate:      ', [Color::DARK_GRAY]);
        $rateColor = $stats['successRate'] >= 90 ? Color::GREEN : ($stats['successRate'] >= 70 ? Color::YELLOW : Color::RED);
        Cli::outln($stats['successRate'] . '%', [$rateColor]);

        Cli::outln('');

        // Status breakdown
        Cli::outln('Status Breakdown:', [Color::BOLD]);
        Cli::out('  Completed:         ', [Color::DARK_GRAY]);
        Cli::outln((string)$stats['completed'], [Color::GREEN]);

        if ($stats['errors'] > 0) {
            Cli::out('  Errors:            ', [Color::DARK_GRAY]);
            Cli::outln((string)$stats['errors'], [Color::RED]);
        }

        if ($stats['interrupted'] > 0) {
            Cli::out('  Interrupted:       ', [Color::DARK_GRAY]);
            Cli::outln((string)$stats['interrupted'], [Color::YELLOW]);
        }

        Cli::outln('');

        // Timing
        Cli::outln('Timing:', [Color::BOLD]);
        Cli::out('  Total Time:        ', [Color::DARK_GRAY]);
        Cli::outln($this->formatTime($stats['totalTime']), [Color::WHITE]);

        Cli::out('  Average Time:      ', [Color::DARK_GRAY]);
        Cli::outln($this->formatTime($stats['avgTime']), [Color::WHITE]);

        Cli::out('  Median Time:       ', [Color::DARK_GRAY]);
        Cli::outln($this->formatTime($stats['medianTime']), [Color::WHITE]);

        Cli::out('  Std Deviation:     ', [Color::DARK_GRAY]);
        Cli::outln($this->formatTime($stats['stdDev']), [Color::WHITE]);

        Cli::outln('');

        // Slowest examples
        if ($slowestCount > 0 && !empty($stats['slowest'])) {
            $this->showExamplesTable(
                'Slowest Examples',
                array_slice($stats['slowest'], 0, $slowestCount),
                $output
            );
        }

        // Fastest examples
        if ($fastestCount > 0 && !empty($stats['fastest'])) {
            $this->showExamplesTable(
                'Fastest Examples',
                array_slice($stats['fastest'], 0, $fastestCount),
                $output
            );
        }

        // Error-prone examples
        $errorProne = array_filter($statuses, fn($s) => count($s->errors) > 1);
        if (!empty($errorProne)) {
            Cli::outln('Frequently Failing Examples:', [Color::BOLD]);
            usort($errorProne, fn($a, $b) => count($b->errors) <=> count($a->errors));
            foreach (array_slice($errorProne, 0, 5) as $status) {
                Cli::out("  [" . ($status->index + 1) . "] ", [Color::DARK_GRAY]);
                Cli::out("{$status->name}", [Color::WHITE]);
                Cli::outln(" (" . count($status->errors) . " failures)", [Color::RED]);
            }
            Cli::outln('');
        }

        return Command::SUCCESS;
    }

    private function showExamplesTable(string $title, array $examples, OutputInterface $output): void
    {
        Cli::outln($title . ':', [Color::BOLD]);

        $table = new Table($output);
        $table->setHeaders(['#', 'Name', 'Group', 'Time', 'Status', 'Path']);

        foreach ($examples as $status) {
            $statusColor = match($status->status) {
                ExecutionStatus::COMPLETED => 'green',
                ExecutionStatus::ERROR => 'red',
                default => 'white',
            };

            $table->addRow([
                $status->index + 1,
                Cli::limit($status->name, 25),
                Cli::limit($status->group, 15),
                $this->formatTime($status->executionTime),
                "<fg={$statusColor}>" . $status->status->value . "</>",
                Cli::limit($status->relativePath, 40),
            ]);
        }

        $table->render();
        Cli::outln('');
    }

    private function calculateStats(array $statuses): array
    {
        // Filter to only successful executions for timing calculations
        $completedStatuses = array_filter($statuses, fn($s) => $s->status === ExecutionStatus::COMPLETED);
        $completedTimes = array_map(fn($s) => $s->executionTime, $completedStatuses);
        $validTimes = array_filter($completedTimes, fn($t) => $t > 0);

        $completed = count($completedStatuses);
        $errors = count(array_filter($statuses, fn($s) => $s->status === ExecutionStatus::ERROR));
        $interrupted = count(array_filter($statuses, fn($s) => $s->status === ExecutionStatus::INTERRUPTED));

        $total = count($statuses);
        $totalTime = array_sum($completedTimes); // Only sum successful executions
        $avgTime = $completed > 0 ? $totalTime / $completed : 0; // Average only successful executions

        // Calculate median
        $sortedTimes = $validTimes;
        sort($sortedTimes);
        $count = count($sortedTimes);
        $medianTime = 0;
        if ($count > 0) {
            $middle = (int) floor($count / 2);
            $medianTime = $count % 2 === 0
                ? ($sortedTimes[$middle - 1] + $sortedTimes[$middle]) / 2
                : $sortedTimes[$middle];
        }

        // Calculate standard deviation
        $stdDev = 0;
        if (count($validTimes) > 1) {
            $avg = array_sum($validTimes) / count($validTimes);
            $variance = array_sum(array_map(fn($t) => pow($t - $avg, 2), $validTimes)) / count($validTimes);
            $stdDev = sqrt($variance);
        }

        // Sort only successful executions for slowest/fastest
        $sorted = $completedStatuses;
        usort($sorted, fn($a, $b) => $b->executionTime <=> $a->executionTime);

        return [
            'total' => $total,
            'completed' => $completed,
            'errors' => $errors,
            'interrupted' => $interrupted,
            'successRate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            'totalTime' => $totalTime,
            'avgTime' => $avgTime,
            'medianTime' => $medianTime,
            'stdDev' => $stdDev,
            'slowest' => $sorted,
            'fastest' => array_reverse($sorted),
        ];
    }

    private function formatTime(float $seconds): string
    {
        if ($seconds < 0.001) {
            return '< 1ms';
        }

        if ($seconds < 1) {
            return round($seconds * 1000) . 'ms';
        }

        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        }

        $minutes = floor($seconds / 60);
        $secs = fmod($seconds, 60);
        return $minutes . 'm ' . round($secs, 1) . 's';
    }

    private function outputJson(array $statuses, int $slowestCount, int $fastestCount): int
    {
        $stats = $this->calculateStats($statuses);

        $data = [
            'overview' => [
                'total' => $stats['total'],
                'completed' => $stats['completed'],
                'errors' => $stats['errors'],
                'interrupted' => $stats['interrupted'],
                'successRate' => $stats['successRate'],
            ],
            'timing' => [
                'totalTime' => round($stats['totalTime'], 4),
                'averageTime' => round($stats['avgTime'], 4),
                'medianTime' => round($stats['medianTime'], 4),
                'standardDeviation' => round($stats['stdDev'], 4),
            ],
        ];

        if ($slowestCount > 0) {
            $data['slowest'] = array_map(
                fn($s) => ['index' => $s->index + 1, 'name' => $s->name, 'time' => $s->executionTime],
                array_slice($stats['slowest'], 0, $slowestCount)
            );
        }

        if ($fastestCount > 0) {
            $data['fastest'] = array_map(
                fn($s) => ['index' => $s->index + 1, 'name' => $s->name, 'time' => $s->executionTime],
                array_slice($stats['fastest'], 0, $fastestCount)
            );
        }

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        echo "\n";

        return Command::SUCCESS;
    }
}
