<?php declare(strict_types=1);

namespace Cognesy\Doctor\Archived;

use Cognesy\Auxiliary\Mintlify\MintlifyIndex;
use Cognesy\Auxiliary\Mintlify\NavigationGroup;
use Cognesy\Auxiliary\Mintlify\NavigationItem;
use Cognesy\Config\BasePath;
use Cognesy\Doctor\Markdown\MarkdownFile;
use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Files;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Use MintlifyDocumentation domain object instead')]
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

    public function makeDocs(): void {
        $this->view->renderHeader();
        $this->updateFiles();
        $this->view->renderUpdate(true);
    }

    public function makeExamplesDocs(): void {
        $this->view->renderHeader();
        $this->initializeBaseFiles();
        $this->processExamplesAndUpdateIndex();
        $this->view->renderUpdate(true);
    }

    public function makePackageDocs(): void {
        $this->view->renderHeader();
        $this->initializeBaseFiles();
        $this->processSubpackageDocs();
        $this->view->renderUpdate(true);
    }

    public function clearDocs(): void {
        $this->view->renderHeader();
        Files::removeDirectory($this->docsTargetDir);
        $this->view->renderUpdate(true);
    }

    private function updateFiles(): void {
        $this->initializeBaseFiles();
        $this->processSubpackageDocs();
        $this->processExamplesAndUpdateIndex();
        $this->view->renderResult(true);
    }

    private function initializeBaseFiles(): void {
        Files::removeDirectory($this->docsTargetDir);
        Files::copyDirectory($this->docsSourceDir, $this->docsTargetDir);
        Files::renameFileExtensions($this->docsTargetDir, 'md', 'mdx');
    }

    private function processSubpackageDocs(): void {
        $this->makeSubpackageDocs('instructor', 'docs-build/instructor');
        $this->makeSubpackageDocs('polyglot', 'docs-build/polyglot');
        $this->makeSubpackageDocs('http-client', 'docs-build/http');
    }

    private function processExamplesAndUpdateIndex(): void {
        $this->updateHubIndex();
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function updateHubIndex(): bool|int {
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

    private function copyExample(Example $example): void {
        if (empty($example->tab)) {
            // skip examples without a tab
            return;
        }

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

    private function getReleaseNotesGroup(): NavigationGroup {
        // get all .mdx files from docs/release-notes
        $releaseNotesFiles = glob(BasePath::get('docs/release-notes/*.mdx'));
        $pages = [];
        foreach ($releaseNotesFiles as $releaseNotesFile) {
            // get file name without extension
            $fileName = pathinfo($releaseNotesFile, PATHINFO_FILENAME);
            $pages[] = str_replace('v', '', $fileName);
        }

        // sort by version x.y.z in descending order
        usort($pages, function ($a, $b) {
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

    private function makeSubpackageDocs(string $subpackage, string $targetDir): bool {
        // copy from packages/instructor/docs to docs/instructor
        $sourcePath = BasePath::get("packages/$subpackage/docs");
        $targetPath = BasePath::get($targetDir);
        Files::removeDirectory($targetPath);
        Files::copyDirectory($sourcePath, $targetPath);
        // process docs in target directory
        $this->inlineExternalCodeblocks($targetPath, $subpackage);
        return true;
    }

    private function inlineExternalCodeblocks(string $targetPath, string $subpackage) : void {
        // inline example code blocks
        $docFiles = array_merge(
            glob("$targetPath/*.mdx"),
            glob("$targetPath/**/*.mdx"),
            glob("$targetPath/*.md"),
            glob("$targetPath/**/*.md"),
        );

        $this->view->renderInlineHeader($subpackage);
        foreach ($docFiles as $docFile) {
            $this->view->renderInlinedItem($docFile, $subpackage);
            $content = file_get_contents(realpath($docFile));
            $markdown = MarkdownFile::fromString($content, realpath($docFile));
            if (!$markdown->hasCodeBlocks()) {
                $this->view->renderInlinedResult('skip');
                continue;
            }
            try {
                $newMarkdown = $this->tryInline($markdown);
                if ($newMarkdown === null) {
                    // no code blocks were replaced, skip this file
                    $this->view->renderInlinedResult('skip');
                    continue; // no replacements made, skip this file
                }
                // if we made replacements, we need to write the file back
                $fileContent = $newMarkdown->toString();
                // write back to file
                file_put_contents($docFile, $fileContent);
                $this->view->renderInlinedResult('ok');
            } catch (\Throwable $e) {
                $this->view->renderInlinedResult('error');
                continue;
            }
        }
    }

    private function tryInline(MarkdownFile $markdown) : ?MarkdownFile {
        $madeReplacements = false;
        $markdownDir = dirname($markdown->path());

        $newMarkdown = $markdown->withReplacedCodeBlocks(function(CodeBlockNode $codeblock) use ($markdownDir, &$madeReplacements) {
            $includePath = $codeblock->metadata('include');
            if (empty($includePath)) {
                return $codeblock;
            }
            $includeDir = trim($includePath, '\'"'); // remove quotes
            
            // Resolve path relative to markdown file
            $path = $markdownDir . '/' . ltrim($includeDir, './');
            
            if (!file_exists($path)) {
                throw new \Exception("Codeblock include file '$path' does not exist (resolved from markdown: {$markdown->path()})");
            }
            $content = file_get_contents($path);
            if ($content === false) {
                throw new \Exception("Failed to read codeblock include file '$path'");
            }
            $madeReplacements = true;
            return $codeblock->withContent($content);
        });

        return match(true) {
            $madeReplacements => $newMarkdown,
            default => null,
        };
    }
}
