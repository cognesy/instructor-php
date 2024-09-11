<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\Instructor\Utils\Cli\Color;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Command;

class ClearDocs extends Command
{
    public string $name = "cleardocs";
    public string $description = "Clear all documentation";

    public function __construct(
    )
    {
    }

    public function execute(array $params = [])
    {
        $arg = $params[0] ?? '';
        $refresh = $this->isRefresh($arg);
        $timeStart = microtime(true);
        Cli::outln("Generating docs...", [Color::BOLD, Color::YELLOW]);
        try {
            $this->docGen->makeDocs();
        } catch (\Exception $e) {
            Cli::outln("Error:", [Color::BOLD, Color::RED]);
            Cli::outln($e->getMessage(), Color::GRAY);
            return;
        }
        $time = round(microtime(true) - $timeStart, 2);
        Cli::outln("Done - in {$time} secs", [Color::BOLD, Color::YELLOW]);
    }

    private function isRefresh(string $arg): bool
    {
        return $arg === 'fresh';
    }
}
