<?php declare(strict_types=1);
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\Runner;
use Cognesy\Utils\Cli\Color;

class RunOneExample extends Command
{
    public string $name = "run";
    public string $description = "Run one example";

    public function __construct(
        private Runner            $runner,
        private ExampleRepository $examples,
    ) {}

    public function execute(array $params = []) {
        $file = $params[0] ?? '';
        if (empty($file)) {
            Cli::outln("Please specify an example to run");
            Cli::outln("You can list available examples with `list` command.\n", Color::DARK_GRAY);
            return;
        }
        $example = $this->examples->argToExample($file);
        if (is_null($example)) {
            Cli::outln("Example not found", [Color::RED]);
            return;
        }
        $this->run($example);
    }

    public function run(Example $example) : void {
        Cli::outln("Executing example: {$example->group}/{$example->name}", [Color::BOLD, Color::YELLOW]);
        $timeStart = microtime(true);
        $this->runner->runSingle($example);
        $timeEnd = microtime(true);
        $totalTime = $timeEnd - $timeStart;
        Cli::out("Example executed in ", [Color::DARK_GRAY]);
        Cli::out(round($totalTime, 2) . " seconds", [Color::BOLD, Color::WHITE]);
    }
}