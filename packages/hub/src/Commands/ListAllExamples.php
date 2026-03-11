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

    #[\Override]
    protected function configure(): void {
        $this
            ->setName('list')
            ->setDescription('List all examples');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        Cli::outln("Listing all examples...", [Color::BOLD, Color::YELLOW]);

        foreach ($this->examples->getAllExamples() as $example) {
            $idDisplay = !empty($example->id) ? 'x' . $example->id : '-----';
            $nameColor = $example->skip ? Color::DARK_GRAY : Color::GREEN;
            $titleColor = Color::DARK_GRAY;
            $indexColor = $example->skip ? Color::DARK_GRAY : Color::WHITE;
            $idColor = $example->skip ? Color::DARK_GRAY : Color::CYAN;
            $tabColor = $example->skip ? Color::DARK_GRAY : Color::DARK_YELLOW;
            Cli::grid([
                [1, '(', STR_PAD_LEFT, Color::DARK_GRAY],
                [3, $example->index, STR_PAD_LEFT, $indexColor],
                [1, ')', STR_PAD_LEFT, Color::DARK_GRAY],
                [1, '[', STR_PAD_LEFT, Color::DARK_GRAY],
                [5, $idDisplay, STR_PAD_RIGHT, $idColor],
                [1, ']', STR_PAD_LEFT, Color::DARK_GRAY],
                [12, $example->tab, STR_PAD_RIGHT, $tabColor],
                [1, '/', STR_PAD_LEFT, Color::DARK_GRAY],
                [16, $example->group, STR_PAD_LEFT, $tabColor],
                [1, '/', STR_PAD_LEFT, Color::DARK_GRAY],
                [20, $example->name, STR_PAD_RIGHT, $nameColor],
                [2, '-', STR_PAD_LEFT, Color::WHITE],
                [40, $example->title, STR_PAD_RIGHT, $titleColor],
            ]);
            Cli::outln();
        }

        return Command::SUCCESS;
    }
}