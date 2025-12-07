<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Contracts\CanTrackExecution;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Data\ExecutionStatus;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Utils\CliMarkdown;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowExample extends Command
{
    public function __construct(
        private ExampleRepository $examples,
        private ?CanTrackExecution $tracker = null,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void {
        $this
            ->setName('show')
            ->setDescription('Show example')
            ->addArgument('example', InputArgument::REQUIRED, 'Example name or index to show');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = $input->getArgument('example');

        if (empty($file)) {
            Cli::outln("Please specify an example to show");
            Cli::outln("You can list available examples with `list` command.\n", Color::DARK_GRAY);
            return Command::FAILURE;
        }

        $example = $this->examples->argToExample($file);
        if (is_null($example)) {
            Cli::outln("Example not found", [Color::RED]);
            return Command::FAILURE;
        }

        Cli::out("Example: ", [Color::DARK_GRAY]);
        Cli::outln($example->name, [Color::BOLD, Color::WHITE]);
        Cli::outln("---");
        Cli::outln();
        Cli::outln();

        $parser = new CliMarkdown;
        $md = $parser->parse($example->content);
        Cli::outln($md);
        Cli::outln("---");

        // Show execution details if tracking is available
        if ($this->tracker) {
            $this->showExecutionDetails($example);
        }

        Cli::outln();
        Cli::outln("Run this example:", [Color::DARK_YELLOW]);
        Cli::out("> ", [Color::DARK_GRAY]);
        Cli::outln("./bin/instructor-hub run {$example->name}", [Color::BOLD, Color::WHITE]);
        Cli::outln();

        return Command::SUCCESS;
    }

    private function showExecutionDetails(Example $example): void
    {
        $status = $this->tracker->getStatus($example);

        if (!$status) {
            Cli::outln();
            Cli::outln("Execution Status:", [Color::BOLD, Color::CYAN]);
            Cli::out("  Status: ", [Color::DARK_GRAY]);
            Cli::outln("Never executed", [Color::DARK_GRAY]);
            return;
        }

        Cli::outln();
        Cli::outln("Execution Status:", [Color::BOLD, Color::CYAN]);

        // Basic info
        Cli::out("  Index: ", [Color::DARK_GRAY]);
        Cli::outln((string)($status->index + 1), [Color::WHITE]);

        Cli::out("  Status: ", [Color::DARK_GRAY]);
        $statusColor = match($status->status) {
            ExecutionStatus::COMPLETED => Color::GREEN,
            ExecutionStatus::ERROR => Color::RED,
            ExecutionStatus::INTERRUPTED => Color::YELLOW,
            ExecutionStatus::RUNNING => Color::CYAN,
            ExecutionStatus::STALE => Color::MAGENTA,
            default => Color::WHITE,
        };
        Cli::outln($status->status->value, [$statusColor, Color::BOLD]);

        if ($status->lastExecuted) {
            Cli::out("  Last Executed: ", [Color::DARK_GRAY]);
            Cli::outln($status->lastExecuted->format('Y-m-d H:i:s'), [Color::WHITE]);
        }

        if ($status->executionTime > 0) {
            Cli::out("  Execution Time: ", [Color::DARK_GRAY]);
            Cli::outln(round($status->executionTime, 2) . "s", [Color::WHITE]);
        }

        Cli::out("  Attempts: ", [Color::DARK_GRAY]);
        Cli::outln((string)$status->attempts, [Color::WHITE]);

        if ($status->exitCode !== 0) {
            Cli::out("  Exit Code: ", [Color::DARK_GRAY]);
            Cli::outln((string)$status->exitCode, [Color::RED]);
        }

        // Show path
        Cli::out("  Path: ", [Color::DARK_GRAY]);
        Cli::outln($status->relativePath, [Color::DARK_GRAY]);

        // Show errors if any
        if (!empty($status->errors)) {
            Cli::outln();
            Cli::outln("  Error History:", [Color::BOLD, Color::RED]);
            foreach (array_slice($status->errors, -3) as $i => $error) {
                Cli::outln("    " . ($i + 1) . ". {$error->type}: {$error->message}", [Color::RED]);
                if (!empty($error->fullOutput) && $error->fullOutput !== $error->message) {
                    Cli::outln("       " . Cli::limit($error->fullOutput, 60), [Color::DARK_GRAY]);
                }
            }
            if (count($status->errors) > 3) {
                Cli::outln("    ... (" . (count($status->errors) - 3) . " more errors)", [Color::DARK_GRAY]);
            }
        }

        // Show recent output excerpt if available
        if (!empty($status->output) && $status->status === ExecutionStatus::COMPLETED) {
            Cli::outln();
            Cli::outln("  Recent Output:", [Color::BOLD, Color::GREEN]);
            $lines = explode("\n", trim($status->output));
            $recentLines = array_slice($lines, -5);
            foreach ($recentLines as $line) {
                Cli::outln("    " . Cli::limit($line, 80), [Color::DARK_GRAY]);
            }
            if (count($lines) > 5) {
                Cli::outln("    ... (" . (count($lines) - 5) . " more lines)", [Color::DARK_GRAY]);
            }
        }
    }
}