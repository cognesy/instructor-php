<?php
namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Utils\Color;
use Cognesy\InstructorHub\Utils\Str;

class DocGenerator
{
    public function __construct(
        private ExampleRepository $examples,
        private string $hubDocsDir,
        private string $mkDocsFile,
        private string $sectionStartMarker,
        private string $sectionEndMarker,
    ) {}

    public function makeDocs() : void {
        // check if hub docs directory exists
        if (!is_dir($this->hubDocsDir)) {
            throw new \Exception("Hub docs directory '$this->hubDocsDir' does not exist");
        }
        Cli::outln("Updating files...", [Color::GRAY]);
        $list = $this->examples->forEachExample(function(Example $example) {
            Cli::out(" [.] ", Color::DARK_GRAY);
            Cli::grid([[22, $example->name, STR_PAD_RIGHT, [Color::BOLD, Color::WHITE]]]);
            $success = $this->copyOrReplace($example);
            if (!$success) {
                Cli::outln("ERROR", [Color::RED]);
                throw new \Exception("Failed to copy or replace example: {$example->name}");
            }
            Cli::outln("DONE", [Color::GREEN]);
            return true;
        });
        Cli::out("Updating mkdocs index... ", [Color::GRAY]);
        if (!$this->updateIndex($list)) {
            Cli::outln("ERROR", [Color::RED]);
            throw new \Exception('Failed to update hub docs index');
        }
        Cli::outln("DONE", [Color::WHITE]);
    }

    private function copyOrReplace(Example $example) : bool {
        // make target md filename - replace .php with .md,
        $newFileName = Str::snake($example->name).'.md';
        $targetPath = $this->hubDocsDir . '/' . $newFileName;
        // copy example file to docs
        if (file_exists($targetPath)) {
            // if the file already exists, replace it
            Cli::grid([[20, "> replacing existing", STR_PAD_RIGHT, Color::DARK_GRAY]]);
            unlink($targetPath);
        } else {
            Cli::grid([[20, "> new doc found", STR_PAD_RIGHT, Color::DARK_YELLOW]]);
        }
        Cli::out("> copying file > ", Color::DARK_GRAY);
        return copy($example->runPath, $targetPath);
    }

    private function updateIndex(array $list) : bool {
        $yamlLines = [];
        foreach ($list as $example) {
            $fileName = Str::snake($example->name).'.md';
            $title = $example->hasTitle ? $example->title : Str::title($example->name);
            $yamlLines[] = "    - $title: 'hub/$fileName'";
        }
        if (empty($yamlLines)) {
            throw new \Exception('No examples found');
        }
        $this->modifyHubIndex($yamlLines);
        return true;
    }

    private function modifyHubIndex(array $indexLines) : bool {
        // get the content of the hub index
        $indexContent = file_get_contents($this->mkDocsFile);
        if ($indexContent === false) {
            throw new \Exception("Failed to read hub index file");
        }

        if (!Str::contains($indexContent, $this->sectionStartMarker)) {
            throw new \Exception("Section start marker not found in hub index");
        }
        if (!Str::contains($indexContent, $this->sectionEndMarker)) {
            throw new \Exception("Section end marker not found in hub index");
        }

        // find the start and end markers
        $lines = explode("\n", $indexContent);
        $preHubLines = [];
        $postHubLines = [];
        $hubSectionFound = false;
        $inHubSection = false;
        foreach ($lines as $line) {
            if (!$hubSectionFound) {
                if (Str::contains($line, $this->sectionStartMarker)) {
                    $hubSectionFound = true;
                    $inHubSection = true;
                    $preHubLines[] = $line;
                } else {
                    $preHubLines[] = $line;
                }
            } elseif ($inHubSection) {
                if (Str::contains($line, $this->sectionEndMarker)) {
                    $postHubLines[] = $line;
                    $inHubSection = false;
                }
            } else {
                $postHubLines[] = $line;
            }
        }
        $outputLines = array_merge($preHubLines, $indexLines, $postHubLines);
        $output = implode("\n", $outputLines);
        // write the new content to the hub index
        return (bool) file_put_contents($this->mkDocsFile, $output);
    }
}
