<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListAllExamples extends Command
{
    public function __construct(
        private ExampleRepository $examples,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->setName('list')
            ->setDescription('List all examples');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        Cli::outln("Listing all examples...", [Color::BOLD, Color::YELLOW]);

        $this->examples->forEachExample(function (Example $example) {
            Cli::grid([
                [1, '(', STR_PAD_LEFT, Color::DARK_GRAY],
                [3, $example->index, STR_PAD_LEFT, Color::WHITE],
                [1, ')', STR_PAD_LEFT, Color::DARK_GRAY],
                [10, $example->tab, STR_PAD_RIGHT, Color::DARK_YELLOW],
                [1, '/', STR_PAD_LEFT, Color::DARK_GRAY],
                [19, $example->group, STR_PAD_LEFT, Color::DARK_YELLOW],
                [1, '/', STR_PAD_LEFT, Color::DARK_GRAY],
                [24, $example->name, STR_PAD_RIGHT, Color::GREEN],
                [2, '-', STR_PAD_LEFT, Color::WHITE],
                [43, $example->title, STR_PAD_RIGHT, Color::DARK_GRAY],
            ]);
            Cli::outln();
            return true;
        });

        return Command::SUCCESS;
    }
}