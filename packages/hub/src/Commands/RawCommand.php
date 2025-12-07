<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RawCommand extends Command
{
    public function __construct(
        private ExampleRepository $examples,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->setName('raw')
            ->setDescription('Run example with raw, unbuffered output (perfect for streaming)')
            ->addArgument('example', InputArgument::REQUIRED, 'Example name or index to run');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('example');

        if (empty($file)) {
            Cli::outln("Please specify an example to run");
            Cli::outln("You can list available examples with `list` command.\n", [Color::DARK_GRAY]);
            return Command::FAILURE;
        }

        $example = $this->examples->argToExample($file);
        if (is_null($example)) {
            Cli::outln("Example not found", [Color::RED]);
            return Command::FAILURE;
        }

        return $this->doRawRun($example);
    }

    private function doRawRun(Example $example): int
    {
        Cli::outln('');
        Cli::outln("Executing example (raw output): {$example->group}/{$example->name}", [Color::BOLD, Color::YELLOW]);
        Cli::outln('');

        $timeStart = microtime(true);

        // Execute as separate PHP process for real-time output
        $command = 'php ' . escapeshellarg($example->runPath);
        $exitCode = 0;

        // Preserve terminal environment for colors and TTY detection
        $env = $_ENV;
        $env['FORCE_COLOR'] = '1';
        $env['TERM'] = $env['TERM'] ?? 'xterm-256color';

        // Use passthru to stream output directly to terminal with preserved environment
        $descriptorSpec = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, null, $env);
        if (is_resource($process)) {
            $exitCode = proc_close($process);
        }

        $timeEnd = microtime(true);
        $totalTime = $timeEnd - $timeStart;

        Cli::outln('');
        if ($exitCode === 0) {
            Cli::out("Status: ", [Color::DARK_GRAY]);
            Cli::outln("OK", [Color::GREEN, Color::BOLD]);
        } else {
            Cli::out("Status: ", [Color::DARK_GRAY]);
            Cli::outln("ERROR", [Color::RED, Color::BOLD]);
        }

        Cli::outln('');
        Cli::out("Example executed in ", [Color::DARK_GRAY]);
        Cli::outln(round($totalTime, 2) . " seconds", [Color::BOLD, Color::WHITE]);
        Cli::outln('');

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}