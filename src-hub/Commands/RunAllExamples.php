<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\Instructor\Utils\Color;
use Cognesy\InstructorHub\Core\Cli;
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
        $arg = $params[0] ?? '';
        if (!empty($arg)) {
            $index = (int) $arg;
        } else {
            $index = 0;
        }

        Cli::outln("Executing all examples...", [Color::BOLD, Color::YELLOW]);
        $timeStart = microtime(true);
        $this->runner->runAll($index);
        $timeEnd = microtime(true);
        $totalTime = $timeEnd - $timeStart;
        Cli::out("All examples executed in ", [Color::DARK_GRAY]);
        Cli::out(round($totalTime, 2) . " seconds", [Color::BOLD, Color::WHITE]);
    }
}