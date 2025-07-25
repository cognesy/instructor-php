<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen;

use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Str;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated]
class MkDocsGenerator
{
    private DocGenView $view;

    public function __construct(
        private ExampleRepository $examples,
        private string $hubDocsDir,
        private string $mkDocsFile,
        private string $sectionStartMarker,
        private string $sectionEndMarker,
    ) {
        $this->view = new DocGenView;
    }

    public function makeDocs(bool $refresh = false) : void {
        // check if hub docs directory exists
        if (!is_dir($this->hubDocsDir)) {
            throw new \Exception("Hub docs directory '$this->hubDocsDir' does not exist");
        }
        $this->view->renderHeader();
        $list = $this->examples->forEachExample(function(Example $example) use ($refresh) {
            $this->view->renderFile($example);
            if ($refresh) {
                $success = $this->replaceAll($example);
            } else {
                $success = $this->replaceNew($example);
            }
            $this->view->renderResult($success);
            if (!$success) {
                throw new \Exception("Failed to copy or replace example: {$example->name}");
            }
            return true;
        });
        $success = $this->updateIndex($list);
        $this->view->renderUpdate($success);
        if (!$success) {
            throw new \Exception('Failed to update hub docs index');
        }
    }

    public function clearDocs() : void {
        $this->view->renderHeader();
        $list = $this->examples->forEachExample(function(Example $example) {
            $this->view->renderFile($example);
            $success = $this->remove($example);
            $this->view->renderResult($success);
            if (!$success) {
                throw new \Exception("Failed to remove example: {$example->name}");
            }
            return true;
        });
        $success = $this->updateIndex($list);
        $this->view->renderUpdate($success);
        if (!$success) {
            throw new \Exception('Failed to update hub docs index');
        }
    }

    private function replaceAll(Example $example) : bool {
        // make target md filename - replace .php with .md,
        $newFileName = Str::snake($example->name).'.md';
        $subdir = Str::snake(substr($example->group, 3));
        $targetPath = $this->hubDocsDir . '/' . $subdir . '/' .$newFileName;
        // copy example file to docs
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
        $this->view->renderNew();
        return $this->copy($example->runPath, $targetPath);
    }

    private function remove(Example $example) : bool {
        // make target md filename - replace .php with .md,
        $newFileName = Str::snake($example->name).'.mdx';
        $subdir = Str::snake(substr($example->group, 3));
        $targetPath = $this->hubDocsDir . '/' . $subdir . '/' .$newFileName;
        // remove example file from docs
        if (!file_exists($targetPath)) {
            return false;
        }
        //unlink($targetPath);
        echo "unlink $targetPath\n";
        return true;
    }

    private function replaceNew(Example $example) : bool {
        // make target md filename - replace .php with .md,
        $subdir = Str::snake(substr($example->group, 3));
        $newFileName = Str::snake($example->name).'.mdx';
        $targetPath = $this->hubDocsDir . '/' . $subdir . '/' .$newFileName;
        // copy example file to docs
        if (file_exists($targetPath)) {
            // compare update dates of $targetPath and $example->runPath
            $targetDate = filemtime($targetPath);
            $exampleDate = filemtime($example->runPath);
            if ($exampleDate > $targetDate) {
                // if the file already exists, replace it
                unlink($targetPath);
            }
            $this->view->renderExists($exampleDate > $targetDate);
            return true;
        }
        $this->view->renderNew();
        return $this->copy($example->runPath, $targetPath);
    }

    private function updateIndex(array $list) : bool {
        $yamlLines = [];
        $subgroup = '';
        foreach ($list as $example) {
            $groupTitle = Str::title(substr($example->group, 3));
            $subdir = Str::snake(substr($example->group, 3));
            if ($subgroup !== $subdir) {
                $yamlLines[] = "    - $groupTitle:";
                $subgroup = $subdir;
            }
            $fileName = Str::snake($example->name).'.md';
            $title = $example->hasTitle ? $example->title : Str::title($example->name);
            $yamlLines[] = "      - $title: 'hub/$subdir/$fileName'";
        }
        if (empty($yamlLines)) {
            throw new \Exception('No examples found');
        }
        $this->modifyHubIndex($yamlLines);
        return true;
    }

    private function copy(string $source, string $destination) : bool {
        // if destination does not exist, create it
        $destDir = dirname($destination);
        if (!is_dir($destDir) && !mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $destDir));
        }
        return copy($source, $destination);
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
