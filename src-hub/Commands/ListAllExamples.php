<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Services\Examples;
use Cognesy\InstructorHub\Utils\Color;

class ListAllExamples extends Command
{
    public string $name = "list";
    public string $description = "List all examples";

    public function __construct(
        public Examples $examples
    ) {}

    public function execute(array $params = []) : void {
        Cli::outln("Listing all examples...", [Color::BOLD, Color::YELLOW]);
        $this->examples->forEachFile(function($file, $index) {
            Cli::grid([
                [1, '(', STR_PAD_LEFT, Color::DARK_GRAY],
                [2, $index, STR_PAD_LEFT, Color::WHITE],
                [1, ')', STR_PAD_LEFT, Color::DARK_GRAY],
                [32, $file, STR_PAD_RIGHT, Color::GREEN],
                [2, '-', STR_PAD_LEFT, Color::WHITE],
                [50, $this->examples->getHeader($file), STR_PAD_RIGHT, Color::DARK_GRAY]
            ]);
//            Cli::out($file, Color::GREEN);
//            //Cli::out(" - ");
//            //Cli::out($this->examples->getHeader($file), Color::GRAY)
            Cli::outln();
            return true;
        });
    }
}