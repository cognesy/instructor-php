<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Services\DocGenerator;
use Cognesy\InstructorHub\Utils\Color;

class GenerateDocs extends Command
{
    public string $name = "gendocs";
    public string $description = "Generate documentation";
    public DocGenerator $docGen;

    public function __construct(DocGenerator $docGen) {
        $this->docGen = $docGen;
    }

    public function execute(array $params = []) {
        $arg = $params[0] ?? '';
        $refresh = $this->isRefresh($arg);
        $timeStart = microtime(true);
        Cli::outln("Generating docs...", [Color::BOLD, Color::YELLOW]);
        try {
            $this->docGen->makeDocs($refresh);
        } catch (\Exception $e) {
            Cli::outln("Error:", [Color::BOLD, Color::RED]);
            Cli::outln($e->getMessage(), Color::GRAY);
            return;
        }
        $time = round(microtime(true) - $timeStart, 2);
        Cli::outln("Done - in {$time} secs", [Color::BOLD, Color::YELLOW]);
    }

    private function isRefresh(string $arg) : bool {
        return $arg === 'fresh';
    }
}
