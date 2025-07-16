<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Services;

use Cognesy\Auxiliary\Mintlify\MintlifyIndex;
use Cognesy\Auxiliary\Mintlify\NavigationGroup;
use Cognesy\Auxiliary\Mintlify\NavigationItem;
use Cognesy\Config\BasePath;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Views\DocGenView;
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

        // make backup copy of mint.json
        //$currentDateTime = date('Y-m-d_H-i-s');
        //$this->copy($this->mintlifyIndexFile, $this->mintlifyIndexFile . '_' . $currentDateTime);

        // update mintlify index
        $this->updateHubIndex();

        $this->view->renderResult(true);
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function updateHubIndex() : bool|int {
        // get the content of the hub index
        $index = MintlifyIndex::fromFile($this->mintlifySourceIndexFile);
        if ($index === false) {
            throw new \Exception("Failed to read hub index file");
        }

        // release notes
        $releaseNotesGroup = $this->getReleaseNotesGroup();
        $index->navigation->removeGroups(['Release Notes']); // TODO: remove this after tests
        $index->navigation->appendGroup($releaseNotesGroup);

        // examples
        $exampleGroups = $this->examples->getExampleGroups();
        foreach ($exampleGroups as $exampleGroup) {
            foreach ($exampleGroup->examples as $example) {
                $this->copyExample($example);
            }
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

    private function getReleaseNotesGroup() : NavigationGroup
    {
        // get all .mdx files from docs/release-notes
        $releaseNotesFiles = glob(BasePath::get('docs/release-notes/*.mdx'));
        $pages = [];
        foreach ($releaseNotesFiles as $releaseNotesFile) {
            // get file name without extension
            $fileName = pathinfo($releaseNotesFile, PATHINFO_FILENAME);
            $pages[] = str_replace('v', '', $fileName);
        }

        // sort by version x.y.z in descending order
        usort($pages, function($a, $b) {
            return version_compare($b, $a);
        });

        // create release notes group
        $releaseNotesGroup = new NavigationGroup('Release Notes');
        $releaseNotesGroup->pages[] = NavigationItem::fromString('release-notes/versions');
        foreach ($pages as $page) {
            $releaseNotesGroup->pages[] = NavigationItem::fromString('release-notes/v' . $page);
        }
        return $releaseNotesGroup;
    }
}
