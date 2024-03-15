<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Color;
use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Services\Runner;

class RunOneExample extends Command
{
    public string $name = "run";
    public string $description = "Run one example";

    public function __construct(
        private Runner $runner,
    ) {}

    public function execute(array $params = []) {
        $file = $params[0] ?? '';
        if (empty($file)) {
            Cli::outln("Please specify an example to run");
            Cli::outln("You can list available examples with `list` command.\n", Color::DARK_GRAY);
            return;
        }
        Cli::outln("Executing all examples...", [Color::BOLD, Color::YELLOW]);
        $this->runner->runFile($file);
    }
}