<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\Runner;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunAllExamples extends Command
{
    public function __construct(
        private Runner $runner,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName('all')
            ->setDescription('Run all examples')
            ->addArgument('index', InputArgument::OPTIONAL, 'Starting index (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $arg = $input->getArgument('index') ?? '';
        $index = !empty($arg) ? (int) $arg : 0;

        Cli::outln("Executing all examples...", [Color::BOLD, Color::YELLOW]);
        $timeStart = microtime(true);
        $this->runner->runAll($index);
        $timeEnd = microtime(true);
        $totalTime = $timeEnd - $timeStart;
        Cli::out("All examples executed in ", [Color::DARK_GRAY]);
        Cli::out(round($totalTime, 2) . " seconds", [Color::BOLD, Color::WHITE]);

        return Command::SUCCESS;
    }
}