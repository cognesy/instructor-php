<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Color;
use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Services\Runner;

class RunAllExamples extends Command
{
    public string $name = "all";
    public string $description = "Run all examples";

    public function __construct(
        private Runner $runner,
    ) {}

    public function execute(array $params = []) {
        Cli::outln("Executing all examples...", [Color::BOLD, Color::YELLOW]);
        $this->runner->runAll();
    }
}