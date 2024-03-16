<?php
namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Data\Example;
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
        $list = $this->examples->forEachExample(function(Example $example) {
            $success = $this->copyOrReplace($example);
            if (!$success) {
                throw new \Exception("Failed to copy or replace example: {$example->name}");
            }
            return true;
        });
//dump($list);
        if (!$this->updateIndex($list)) {
            throw new \Exception('Failed to update hub docs index');
        }
    }

    private function copyOrReplace(Example $example) : bool {
        // make target md filename - replace .php with .md,
        $newFileName = Str::snake($example->name).'.md';
        $targetPath = $this->hubDocsDir . '/' . $newFileName;
        // copy example file to docs
        if (file_exists($targetPath)) {
            // if the file already exists, replace it
            unlink($targetPath);
        }
        return copy($example->runPath, $targetPath);
    }

    private function updateIndex(array $list) : bool {
#    - Single Classification Model: 'hub/single_classification.md'
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
