<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class HubHelpCommand extends Command
{
    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('help')
            ->setDescription('Display information about Hub and available commands')
            ->addArgument('command_name', InputArgument::OPTIONAL, 'Show help for specific command');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $specificCommand = $input->getArgument('command_name');

        if (is_string($specificCommand) && $specificCommand !== 'help') {
            // Show help for specific command using parent application
            $command = $this->getApplication()->find($specificCommand);
            $command->run(new \Symfony\Component\Console\Input\ArrayInput(['--help' => true]), $output);
            return Command::SUCCESS;
        }

        // Show Hub application help
        $this->showHubHelp();

        return Command::SUCCESS;
    }

    private function showHubHelp(): void
    {
        Cli::outln('');
        Cli::outln('Hub - Example Execution & Tracking', [Color::BOLD, Color::YELLOW]);
        Cli::outln('====================================', [Color::YELLOW]);
        Cli::outln('');

        Cli::outln('Hub provides example execution with comprehensive status tracking,', [Color::WHITE]);
        Cli::outln('selective re-execution, and performance analytics.', [Color::WHITE]);
        Cli::outln('');

        Cli::outln('QUICK START:', [Color::BOLD, Color::CYAN]);
        Cli::out('  composer hub run 1              ', [Color::DARK_GRAY]);
        Cli::outln('Run single example (raw output, colors preserved)', [Color::WHITE]);

        Cli::out('  composer hub list               ', [Color::DARK_GRAY]);
        Cli::outln('List all examples', [Color::WHITE]);

        Cli::out('  composer hub all                ', [Color::DARK_GRAY]);
        Cli::outln('Run all examples with tracking', [Color::WHITE]);

        Cli::out('  composer hub status             ', [Color::DARK_GRAY]);
        Cli::outln('Check execution status', [Color::WHITE]);

        Cli::out('  composer hub stats              ', [Color::DARK_GRAY]);
        Cli::outln('View performance analytics', [Color::WHITE]);

        Cli::outln('');

        Cli::outln('CORE COMMANDS:', [Color::BOLD, Color::CYAN]);

        Cli::out('  list                            ', [Color::GREEN, Color::BOLD]);
        Cli::outln('List all available examples', [Color::WHITE]);

        Cli::out('  run <example> [--track]         ', [Color::GREEN, Color::BOLD]);
        Cli::outln('Run single example (raw output by default)', [Color::WHITE]);

        Cli::out('  raw <example>                   ', [Color::GREEN, Color::BOLD]);
        Cli::outln('Run example with raw, unbuffered output', [Color::WHITE]);

        Cli::out('  all [start] [--filter=X]        ', [Color::GREEN, Color::BOLD]);
        Cli::outln('Run all/bulk examples with tracking', [Color::WHITE]);

        Cli::out('  errors                          ', [Color::GREEN, Color::BOLD]);
        Cli::outln('Re-run failed examples', [Color::WHITE]);

        Cli::out('  stale                           ', [Color::GREEN, Color::BOLD]);
        Cli::outln('Run examples with modified files', [Color::WHITE]);

        Cli::out('  status [--detailed] [--format]  ', [Color::GREEN, Color::BOLD]);
        Cli::outln('Show execution status and summaries', [Color::WHITE]);

        Cli::out('  stats [--slowest=N]             ', [Color::GREEN, Color::BOLD]);
        Cli::outln('Show performance metrics and analytics', [Color::WHITE]);

        Cli::out('  clean [--completed] [--all]     ', [Color::GREEN, Color::BOLD]);
        Cli::outln('Clean status data', [Color::WHITE]);

        Cli::out('  show <example>                  ', [Color::GREEN, Color::BOLD]);
        Cli::outln('Show details for specific example', [Color::WHITE]);

        Cli::outln('');

        Cli::outln('EXAMPLES:', [Color::BOLD, Color::CYAN]);

        Cli::out('  composer hub run 35             ', [Color::DARK_GRAY]);
        Cli::outln('# Real-time streaming output with colors', [Color::MAGENTA]);

        Cli::out('  composer hub all --filter=errors ', [Color::DARK_GRAY]);
        Cli::outln('# Re-run only failed examples', [Color::MAGENTA]);

        Cli::out('  composer hub status --detailed   ', [Color::DARK_GRAY]);
        Cli::outln('# Per-example breakdown', [Color::MAGENTA]);

        Cli::out('  composer hub stats --slowest=10  ', [Color::DARK_GRAY]);
        Cli::outln('# Show 10 slowest examples', [Color::MAGENTA]);

        Cli::outln('');

        Cli::outln('For detailed help on any command:', [Color::BOLD]);
        Cli::out('  composer hub help <command>', [Color::YELLOW, Color::BOLD]);
        Cli::outln('');

        Cli::outln('For full documentation:', [Color::BOLD]);
        Cli::out('  packages/hub/README.md', [Color::YELLOW, Color::BOLD]);
        Cli::outln('');
        Cli::outln('');
    }
}