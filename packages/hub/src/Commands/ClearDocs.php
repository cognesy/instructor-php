<?php
namespace Cognesy\InstructorHub\Commands;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Core\Command;
use Cognesy\InstructorHub\Services\MintlifyDocGenerator;
use Cognesy\Utils\Cli\Color;

class ClearDocs extends Command
{
    public string $name = "cleardocs";
    public string $description = "Clear all documentation";
    public MintlifyDocGenerator $docGen;

    public function __construct(
        MintlifyDocGenerator $docGen
    ) {
        $this->docGen = $docGen;
    }

    public function execute(array $params = [])
    {
        $timeStart = microtime(true);
        Cli::outln("Clearing all docs...", [Color::BOLD, Color::YELLOW]);
        try {
            $this->docGen->clearDocs();
        } catch (\Exception $e) {
            Cli::outln("Error:", [Color::BOLD, Color::RED]);
            Cli::outln($e->getMessage(), Color::GRAY);
            return;
        }
        $time = round(microtime(true) - $timeStart, 2);
        Cli::outln("Done - in {$time} secs", [Color::BOLD, Color::YELLOW]);
    }
}
