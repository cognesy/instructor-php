<?php

namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Utils\Mintlify\Index;
use Cognesy\InstructorHub\Views\DocGenView;

class MintlifyDocGenerator
{
    private DocGenView $view;

    public function __construct(
        private ExampleRepository $examples,
        private string $mintlifyCookbookDir,
        private string $mintlifyIndexFile,
    ) {
        $this->view = new DocGenView;
    }

    public function makeDocs() : void {
        if (!is_dir($this->mintlifyCookbookDir)) {
            throw new \Exception("Hub docs directory '$this->mintlifyCookbookDir' does not exist");
        }
        $this->view->renderHeader();
        $this->updateFiles();
        $this->view->renderUpdate(true);
    }

    private function updateFiles() : void {
        //$this->removeDir($this->mintlifyCookbookDir . '/examples');
        $groups = $this->examples->getExampleGroups();
        foreach ($groups as $group) {
            foreach ($group->examples as $example) {
                $this->view->renderFile($example);
                $targetFilePath = $this->mintlifyCookbookDir . $example->toDocPath() . '.mdx';

                // get last update date of source file
                $sourceFileLastUpdate = filemtime($example->runPath);
                // get last update date of target file
                $targetFile = $this->mintlifyCookbookDir . $example->toDocPath() . '.mdx';
                $targetFileExists = file_exists($targetFile);

                if ($targetFileExists) {
                    $targetFileLastUpdate = filemtime($targetFile);
                    // if source file is older than target file, skip
                    if ($sourceFileLastUpdate > $targetFileLastUpdate) {
                        // remove target file
                        unlink($targetFile);
                        $this->copy($example->runPath, $targetFilePath);
                        $this->view->renderExists(true);
                    } else {
                        $this->view->renderExists(false);
                    }
                } else {
                    $this->copy($example->runPath, $targetFilePath);
                    $this->view->renderNew();
                }
                $this->view->renderResult(true);
            }
        }
        // make backup copy of mint.json
        $currentDateTime = date('Y-m-d_H-i-s');
        $this->copy($this->mintlifyIndexFile, $this->mintlifyIndexFile . '_' . $currentDateTime);
        // update mint.json
        $this->updateHubIndex($groups);
        $this->view->renderResult(true);
    }

    private function updateHubIndex(array $exampleGroups) : bool {
        // get the content of the hub index
        $index = Index::fromFile($this->mintlifyIndexFile);
        if ($index === false) {
            throw new \Exception("Failed to read hub index file");
        }
        $index->navigation->removeGroups(['Basics', 'Advanced', 'Techniques', 'Troubleshooting', 'LLM API Support']);
        foreach ($exampleGroups as $exampleGroup) {
            $index->navigation->appendGroup($exampleGroup->toNavigationGroup());
        }
        return $index->saveFile($this->mintlifyIndexFile);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function removeDir(string $path) : void {
        $files = glob($path . '/*');
        foreach ($files as $file) {
            is_dir($file) ? $this->removeDir($file) : unlink($file);
        }
        rmdir($path);
    }

    private function copy(string $source, string $destination) : void {
        // if destination does not exist, create it
        $destDir = dirname($destination);
        if (!is_dir($destDir) && !mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $destDir));
        }
        copy($source, $destination);
    }
}
