<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;

class ListAllExamples extends Command
{
    public string $name = "list";
    public string $description = "List all examples";

    public function __construct(
        public ExampleRepository $examples
    ) {}

    public function execute(array $params = []) : void {
        Cli::outln("Listing all examples...", [Color::BOLD, Color::YELLOW]);
        $this->examples->forEachExample(function(Example $example) {
            Cli::grid([
                [1, '(', STR_PAD_LEFT, Color::DARK_GRAY],
                [2, $example->index, STR_PAD_LEFT, Color::WHITE],
                [1, ')', STR_PAD_LEFT, Color::DARK_GRAY],
                [10, $example->tab, STR_PAD_RIGHT, Color::DARK_YELLOW],
                [1, '/', STR_PAD_LEFT, Color::DARK_GRAY],
                [15, $example->group, STR_PAD_LEFT, Color::DARK_YELLOW],
                [1, '/', STR_PAD_LEFT, Color::DARK_GRAY],
                [24, $example->name, STR_PAD_RIGHT, Color::GREEN],
                [2, '-', STR_PAD_LEFT, Color::WHITE],
                [50, $example->title, STR_PAD_RIGHT, Color::DARK_GRAY]
            ]);
            Cli::outln();
            return true;
        });
    }
}