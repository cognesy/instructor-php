<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\Runner;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunOneExample extends Command
{
    public function __construct(
        private Runner $runner,
        private ExampleRepository $examples,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName('run')
            ->setDescription('Run one example')
            ->addArgument('example', InputArgument::REQUIRED, 'Example name or index to run');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $file = $input->getArgument('example');

        if (empty($file)) {
            Cli::outln("Please specify an example to run");
            Cli::outln("You can list available examples with `list` command.\n", Color::DARK_GRAY);
            return Command::FAILURE;
        }

        $example = $this->examples->argToExample($file);
        if (is_null($example)) {
            Cli::outln("Example not found", [Color::RED]);
            return Command::FAILURE;
        }

        $this->doRun($example);
        return Command::SUCCESS;
    }

    public function doRun(Example $example): void {
        Cli::outln("Executing example: {$example->group}/{$example->name}", [Color::BOLD, Color::YELLOW]);
        $timeStart = microtime(true);
        $this->runner->runSingle($example);
        $timeEnd = microtime(true);
        $totalTime = $timeEnd - $timeStart;
        Cli::out("Example executed in ", [Color::DARK_GRAY]);
        Cli::out(round($totalTime, 2) . " seconds", [Color::BOLD, Color::WHITE]);
    }
}