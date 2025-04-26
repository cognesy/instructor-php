<?php

namespace Cognesy\InstructorHub\Services;

use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Utils\Mintlify\MintlifyIndex;
use Cognesy\InstructorHub\Views\DocGenView;
use Cognesy\Utils\BasePath;
use Cognesy\Utils\Files;

class MintlifyDocGenerator
{
    private DocGenView $view;

    public function __construct(
        private ExampleRepository $examples,
        private string $docsSourceDir,
        private string $docsTargetDir,
        private string $cookbookTargetDir,
        private string $mintlifySourceIndexFile,
        private string $mintlifyTargetIndexFile,
        private array $dynamicGroups,
    ) {
        $this->view = new DocGenView;
    }

    public function makeDocs() : void {
        //        if (!is_dir($this->cookbookTargetDir)) {
        //            throw new \Exception("Hub docs directory '$this->cookbookTargetDir' does not exist");
        //        }
        $this->view->renderHeader();
        $this->updateFiles();
        $this->view->renderUpdate(true);
    }

    public function clearDocs() : void {
        $this->view->renderHeader();
        // get only subdirectories of mintlifyCookbookDir
        //        $subdirs = array_filter(glob($this->cookbookTargetDir . '/*'), 'is_dir');
        //        foreach ($subdirs as $subdir) {
        //            $this->removeDir($subdir);
        //        }
        Files::removeDirectory($this->docsTargetDir);
        $this->view->renderUpdate(true);
    }

    private function updateFiles() : void {
        Files::removeDirectory($this->docsTargetDir);
        Files::copyDirectory($this->docsSourceDir, $this->docsTargetDir);
        $this->copySubpackageDocs('instructor', 'docs-build/instructor');
        $this->copySubpackageDocs('polyglot', 'docs-build/polyglot');
        $this->copySubpackageDocs('http-client', 'docs-build/http');
        Files::renameFileExtensions($this->docsTargetDir, 'md', 'mdx');

        $groups = $this->examples->getExampleGroups();
        foreach ($groups as $group) {
            foreach ($group->examples as $example) {
                $this->copyExample($example);
            }
        }
        // make backup copy of mint.json
        //$currentDateTime = date('Y-m-d_H-i-s');
        //$this->copy($this->mintlifyIndexFile, $this->mintlifyIndexFile . '_' . $currentDateTime);
        // update mint.json
        $this->updateHubIndex($groups);
        $this->view->renderResult(true);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function updateHubIndex(array $exampleGroups) : bool {
        // get the content of the hub index
        $index = MintlifyIndex::fromFile($this->mintlifySourceIndexFile);
        if ($index === false) {
            throw new \Exception("Failed to read hub index file");
        }
        $index->navigation->removeGroups($this->dynamicGroups); // TODO: remove this after tests
        foreach ($exampleGroups as $exampleGroup) {
            $index->navigation->appendGroup($exampleGroup->toNavigationGroup());
        }
        return $index->saveFile($this->mintlifyTargetIndexFile);
    }

    private function copyExample(Example $example) : void {
        $this->view->renderFile($example);
        $targetFilePath = $this->cookbookTargetDir . $example->toDocPath() . '.mdx';

        // get last update date of source file
        $sourceFileLastUpdate = filemtime($example->runPath);
        // get last update date of target file
        $targetFile = $this->cookbookTargetDir . $example->toDocPath() . '.mdx';
        $targetFileExists = file_exists($targetFile);

        if ($targetFileExists) {
            $targetFileLastUpdate = filemtime($targetFile);
            // if source file is older than target file, skip
            if ($sourceFileLastUpdate > $targetFileLastUpdate) {
                // remove target file
                unlink($targetFile);
                Files::copyFile($example->runPath, $targetFilePath);
                $this->view->renderExists(true);
            } else {
                $this->view->renderExists(false);
            }
        } else {
            Files::copyFile($example->runPath, $targetFilePath);
            $this->view->renderNew();
        }
        $this->view->renderResult(true);
    }

    private function copySubpackageDocs(string $subpackage, string $targetDir, bool $renameToMdx = false) : bool {
        // copy from packages/instructor/docs to docs/instructor
        Files::removeDirectory(BasePath::get($targetDir));
        Files::copyDirectory(
            BasePath::get("packages/$subpackage/docs"),
            BasePath::get($targetDir),
        );
        if ($renameToMdx) {
            Files::renameFileExtensions(
                BasePath::get($targetDir),
                'md',
                'mdx',
            );
        }
        return true;
    }
}
