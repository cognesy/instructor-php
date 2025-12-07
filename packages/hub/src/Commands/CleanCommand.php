<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Contracts\CanPersistStatus;
use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\ExecutionTracker;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CleanCommand extends Command
{
    public function __construct(
        private ExecutionTracker $tracker,
        private CanPersistStatus $repository,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('clean')
            ->setDescription('Clean execution status data')
            ->addOption('all', 'a', InputOption::VALUE_NONE,
                'Remove all status data')
            ->addOption('completed', 'c', InputOption::VALUE_NONE,
                'Remove status for completed examples only')
            ->addOption('older-than', 'o', InputOption::VALUE_REQUIRED,
                'Remove status older than specified time (e.g. "1 week", "1 month")')
            ->addOption('backup', 'b', InputOption::VALUE_NONE,
                'Create backup before cleaning')
            ->addOption('force', 'f', InputOption::VALUE_NONE,
                'Skip confirmation prompt')
            ->addOption('no-output', null, InputOption::VALUE_NONE,
                'Suppress output');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $cleanAll = $input->getOption('all');
        $cleanCompleted = $input->getOption('completed');
        $olderThan = $input->getOption('older-than');
        $backup = $input->getOption('backup');
        $force = $input->getOption('force');
        $silent = $input->getOption('no-output');

        // Check if any cleaning option is specified
        if (!$cleanAll && !$cleanCompleted && !$olderThan) {
            if (!$silent) {
                Cli::outln('');
                Cli::outln('Please specify what to clean:', [Color::YELLOW]);
                Cli::outln('  --all          Remove all status data', [Color::DARK_GRAY]);
                Cli::outln('  --completed    Remove completed examples only', [Color::DARK_GRAY]);
                Cli::outln('  --older-than   Remove data older than specified time', [Color::DARK_GRAY]);
                Cli::outln('');
            }
            return Command::FAILURE;
        }

        // Check if status file exists
        if ($this->repository->exists() === false) {
            if ($silent === false) {
                Cli::outln('');
                Cli::outln('No status file exists. Nothing to clean.', [Color::YELLOW]);
                Cli::outln('');
            }
            return Command::SUCCESS;
        }

        // Describe what will be cleaned
        $description = $this->getCleanDescription($cleanAll, $cleanCompleted, $olderThan);

        // Confirmation
        if (!$force && !$silent) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "\nAre you sure you want to {$description}? [y/N] ",
                false
            );

            if (!$helper->ask($input, $output, $question)) {
                Cli::outln('Operation cancelled.', [Color::YELLOW]);
                return Command::SUCCESS;
            }
        }

        // Create backup if requested
        if ($backup) {
            try {
                $backupPath = $this->repository->backup();
                if (!$silent) {
                    Cli::outln('');
                    Cli::outln("Backup created: {$backupPath}", [Color::GREEN]);
                }
            } catch (\Exception $e) {
                if (!$silent) {
                    Cli::outln("Failed to create backup: " . $e->getMessage(), [Color::RED]);
                }
                return Command::FAILURE;
            }
        }

        // Perform cleaning
        $removed = 0;

        if ($cleanAll) {
            $this->tracker->clearAllStatuses();
            if (!$silent) {
                Cli::outln('');
                Cli::outln('All status data has been removed.', [Color::GREEN]);
            }
            return Command::SUCCESS;
        }

        if ($cleanCompleted) {
            $removed = $this->tracker->removeCompletedStatuses();
            if (!$silent) {
                Cli::outln('');
                Cli::outln("Removed {$removed} completed example status(es).", [Color::GREEN]);
            }
        }

        if ($olderThan) {
            try {
                $interval = \DateInterval::createFromDateString($olderThan);
                if ($interval === false) {
                    throw new \Exception('Invalid interval');
                }
                $removed += $this->tracker->removeOlderThan($interval);
                if (!$silent) {
                    Cli::outln('');
                    Cli::outln("Removed {$removed} status(es) older than {$olderThan}.", [Color::GREEN]);
                }
            } catch (\Exception $e) {
                if (!$silent) {
                    Cli::outln("Invalid time format: {$olderThan}", [Color::RED]);
                    Cli::outln('Examples: "1 week", "1 month", "30 days"', [Color::DARK_GRAY]);
                }
                return Command::FAILURE;
            }
        }

        // Show remaining stats
        if (!$silent) {
            $summary = $this->tracker->getSummary();
            Cli::outln('');
            Cli::outln("Remaining: {$summary->executed} example status(es)", [Color::DARK_GRAY]);
            Cli::outln('');
        }

        return Command::SUCCESS;
    }

    private function getCleanDescription(bool $all, bool $completed, ?string $olderThan): string
    {
        $parts = [];

        if ($all) {
            return 'remove ALL status data';
        }

        if ($completed) {
            $parts[] = 'completed examples';
        }

        if ($olderThan) {
            $parts[] = "data older than {$olderThan}";
        }

        return 'remove status for ' . implode(' and ', $parts);
    }
}
