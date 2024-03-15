<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Services\Examples;
use Cognesy\InstructorHub\Services\Runner;
use Cognesy\InstructorHub\Utils\Color;

class RunOneExample extends Command
{
    public string $name = "run";
    public string $description = "Run one example";

    public function __construct(
        private Runner $runner,
        private Examples $examples,
    ) {}

    public function execute(array $params = []) {
        $file = $params[0] ?? '';
        $file = $this->examples->exampleName($file);
        if (empty($file)) {
            Cli::outln("Please specify an example to run");
            Cli::outln("You can list available examples with `list` command.\n", Color::DARK_GRAY);
            return;
        }
        if (!$this->examples->exampleExists($file)) {
            Cli::outln("Example not found", [Color::RED]);
            return;
        }
        $this->runner->runSingle($file);
    }
}