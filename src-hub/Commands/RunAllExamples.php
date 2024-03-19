<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Services\Runner;
use Cognesy\InstructorHub\Utils\Color;

class RunAllExamples extends Command
{
    public string $name = "all";
    public string $description = "Run all examples";

    public function __construct(
        private Runner $runner,
    ) {}

    public function execute(array $params = []) {
        Cli::outln("Executing all examples...", [Color::BOLD, Color::YELLOW]);
        $timeStart = microtime(true);
        $this->runner->runAll();
        $timeEnd = microtime(true);
        $totalTime = $timeEnd - $timeStart;
        Cli::out("All examples executed in ", [Color::DARK_GRAY]);
        Cli::out(round($totalTime, 2) . " seconds", [Color::BOLD, Color::WHITE]);
    }
}