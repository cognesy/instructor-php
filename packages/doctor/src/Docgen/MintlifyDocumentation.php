<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen;

use Cognesy\Auxiliary\Mintlify\MintlifyIndex;
use Cognesy\Auxiliary\Mintlify\NavigationGroup;
use Cognesy\Auxiliary\Mintlify\NavigationItem;
use Cognesy\Config\BasePath;
use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\Data\FileProcessingResult;
use Cognesy\Doctor\Docgen\Data\GenerationResult;
use Cognesy\Doctor\Markdown\MarkdownFile;
use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;
use Cognesy\InstructorHub\Data\Example;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Files;

class MintlifyDocumentation
{
    public function __construct(
        private ExampleRepository $examples,
        private DocumentationConfig $config,
    ) {}

    public function generateAll(): GenerationResult
    {
        $startTime = microtime(true);
        $filesProcessed = 0;
        $filesCreated = 0;
        $filesUpdated = 0;
        $errors = [];

        try {
            $this->initializeBaseFiles();
            
            $packageResult = $this->generatePackageDocs();
            $exampleResult = $this->generateExampleDocs();

            $filesProcessed = $packageResult->filesProcessed + $exampleResult->filesProcessed;
            $filesCreated = $packageResult->filesCreated + $exampleResult->filesCreated;
            $filesUpdated = $packageResult->filesUpdated + $exampleResult->filesUpdated;
            $errors = [...$packageResult->errors, ...$exampleResult->errors];

            $duration = microtime(true) - $startTime;

            return GenerationResult::success(
                filesProcessed: $filesProcessed,
                filesCreated: $filesCreated,
                filesUpdated: $filesUpdated,
                duration: $duration,
                message: 'All documentation generated successfully'
            );

        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                filesProcessed: $filesProcessed,
                duration: microtime(true) - $startTime,
                message: 'Documentation generation failed'
            );
        }
    }

    public function generatePackageDocs(): GenerationResult
    {
        $startTime = microtime(true);
        $filesProcessed = 0;
        $errors = [];

        try {
            $packages = ['instructor', 'polyglot', 'http-client'];
            $targetDirs = ['docs-build/instructor', 'docs-build/polyglot', 'docs-build/http'];

            foreach (array_combine($packages, $targetDirs) as $package => $targetDir) {
                $result = $this->processPackage($package, $targetDir);
                $filesProcessed += $result->filesProcessed;
                if (!$result->isSuccess()) {
                    array_push($errors, ...$result->errors);
                }
            }

            $duration = microtime(true) - $startTime;

            return GenerationResult::success(
                filesProcessed: $filesProcessed,
                duration: $duration,
                message: 'Package documentation generated successfully'
            );

        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                filesProcessed: $filesProcessed,
                duration: microtime(true) - $startTime,
                message: 'Package documentation generation failed'
            );
        }
    }

    public function generateExampleDocs(): GenerationResult
    {
        $startTime = microtime(true);
        $filesProcessed = 0;
        $filesCreated = 0;
        $filesUpdated = 0;
        $filesSkipped = 0;
        $errors = [];

        try {
            $result = $this->updateHubIndex();
            if (!$result->isSuccess()) {
                return GenerationResult::failure(
                    errors: $result->errors,
                    duration: microtime(true) - $startTime,
                    message: 'Failed to update hub index'
                );
            }

            $exampleGroups = $this->examples->getExampleGroups();
            foreach ($exampleGroups as $exampleGroup) {
                foreach ($exampleGroup->examples as $example) {
                    $processResult = $this->processExample($example);
                    $filesProcessed++;
                    
                    if ($processResult->isSuccess()) {
                        match($processResult->action) {
                            'created' => $filesCreated++,
                            'updated' => $filesUpdated++,
                            'skipped' => $filesSkipped++,
                        };
                    } else {
                        $errors[] = $processResult->message;
                    }
                }
            }

            $duration = microtime(true) - $startTime;

            return GenerationResult::success(
                filesProcessed: $filesProcessed,
                filesSkipped: $filesSkipped,
                filesCreated: $filesCreated,
                filesUpdated: $filesUpdated,
                duration: $duration,
                message: 'Example documentation generated successfully'
            );

        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                filesProcessed: $filesProcessed,
                duration: microtime(true) - $startTime,
                message: 'Example documentation generation failed'
            );
        }
    }

    public function clearDocumentation(): GenerationResult
    {
        $startTime = microtime(true);
        
        try {
            Files::removeDirectory($this->config->docsTargetDir);
            
            return GenerationResult::success(
                duration: microtime(true) - $startTime,
                message: 'Documentation cleared successfully'
            );
        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                duration: microtime(true) - $startTime,
                message: 'Failed to clear documentation'
            );
        }
    }

    // Public methods for standalone command execution
    
    public function initializeBaseFiles(): void
    {
        Files::removeDirectory($this->config->docsTargetDir);
        Files::copyDirectory($this->config->docsSourceDir, $this->config->docsTargetDir);
        Files::renameFileExtensions($this->config->docsTargetDir, 'md', 'mdx');
    }

    // Private methods - Domain operations

    private function processPackage(string $package, string $targetDir): GenerationResult
    {
        $startTime = microtime(true);
        $filesProcessed = 0;
        
        try {
            $sourcePath = BasePath::get("packages/$package/docs");
            $targetPath = BasePath::get($targetDir);
            
            Files::removeDirectory($targetPath);
            Files::copyDirectory($sourcePath, $targetPath);
            Files::renameFileExtensions($targetPath, 'md', 'mdx');
            
            $this->inlineExternalCodeblocks($targetPath, $package);
            $filesProcessed++;

            return GenerationResult::success(
                filesProcessed: $filesProcessed,
                duration: microtime(true) - $startTime
            );

        } catch (\Throwable $e) {
            return GenerationResult::failure(
                errors: [$e->getMessage()],
                duration: microtime(true) - $startTime
            );
        }
    }

    private function processExample(Example $example): FileProcessingResult
    {
        if (empty($example->tab)) {
            return FileProcessingResult::skipped($example->name, 'No tab specified');
        }

        try {
            $targetFilePath = $this->config->cookbookTargetDir . $example->toDocPath() . '.mdx';
            $sourceFileLastUpdate = filemtime($example->runPath);
            $targetFileExists = file_exists($targetFilePath);

            if ($targetFileExists) {
                $targetFileLastUpdate = filemtime($targetFilePath);
                if ($sourceFileLastUpdate > $targetFileLastUpdate) {
                    unlink($targetFilePath);
                    Files::copyFile($example->runPath, $targetFilePath);
                    return FileProcessingResult::updated($targetFilePath, 'Source file updated');
                } else {
                    return FileProcessingResult::skipped($targetFilePath, 'No changes detected');
                }
            } else {
                Files::copyFile($example->runPath, $targetFilePath);
                return FileProcessingResult::created($targetFilePath, 'New example file');
            }

        } catch (\Throwable $e) {
            return FileProcessingResult::error($example->name, $e->getMessage(), $e);
        }
    }

    private function updateHubIndex(): GenerationResult
    {
        try {
            $index = MintlifyIndex::fromFile($this->config->mintlifySourceIndexFile);
            if ($index === false) {
                return GenerationResult::failure(['Failed to read hub index file']);
            }

            // Release notes
            $releaseNotesGroup = $this->getReleaseNotesGroup();
            $index->navigation->removeGroups(['Release Notes']);
            $index->navigation->appendGroup($releaseNotesGroup);

            // Examples
            $exampleGroups = $this->examples->getExampleGroups();
            $index->navigation->removeGroups($this->config->dynamicGroups);
            foreach ($exampleGroups as $exampleGroup) {
                $index->navigation->appendGroup($exampleGroup->toNavigationGroup());
            }

            $result = $index->saveFile($this->config->mintlifyTargetIndexFile);
            
            return $result 
                ? GenerationResult::success(message: 'Hub index updated')
                : GenerationResult::failure(['Failed to save hub index file']);

        } catch (\Throwable $e) {
            return GenerationResult::failure([$e->getMessage()]);
        }
    }

    private function getReleaseNotesGroup(): NavigationGroup
    {
        $releaseNotesFiles = glob(BasePath::get('docs/release-notes/*.mdx'));
        $pages = [];
        
        foreach ($releaseNotesFiles as $releaseNotesFile) {
            $fileName = pathinfo($releaseNotesFile, PATHINFO_FILENAME);
            $pages[] = str_replace('v', '', $fileName);
        }

        usort($pages, fn($a, $b) => version_compare($b, $a));

        $releaseNotesGroup = new NavigationGroup('Release Notes');
        $releaseNotesGroup->pages[] = NavigationItem::fromString('release-notes/versions');
        foreach ($pages as $page) {
            $releaseNotesGroup->pages[] = NavigationItem::fromString('release-notes/v' . $page);
        }
        
        return $releaseNotesGroup;
    }

    private function inlineExternalCodeblocks(string $targetPath, string $subpackage): void
    {
        $docFiles = array_merge(
            glob("$targetPath/*.mdx"),
            glob("$targetPath/**/*.mdx"),
            glob("$targetPath/*.md"),
            glob("$targetPath/**/*.md"),
        );

        foreach ($docFiles as $docFile) {
            $content = file_get_contents(realpath($docFile));
            $markdown = MarkdownFile::fromString($content);
            
            if (!$markdown->hasCodeBlocks()) {
                continue;
            }

            try {
                $newMarkdown = $this->tryInlineCodeblocks($markdown, $this->config->codeblocksDir);
                if ($newMarkdown !== null) {
                    file_put_contents($docFile, $newMarkdown->toString());
                }
            } catch (\Throwable $e) {
                // Continue processing other files
            }
        }
    }

    private function tryInlineCodeblocks(MarkdownFile $markdown, string $codeblocksPath): ?MarkdownFile
    {
        $madeReplacements = false;

        $newMarkdown = $markdown->withReplacedCodeBlocks(function(CodeBlockNode $codeblock) use ($codeblocksPath, &$madeReplacements) {
            $includePath = $codeblock->metadata('include');
            if (empty($includePath)) {
                return $codeblock;
            }
            
            $includeDir = trim($includePath, '\'"');
            $path = $codeblocksPath . '/' . $includeDir;
            
            if (!file_exists($path)) {
                throw new \Exception("Codeblock include file '$path' does not exist");
            }
            
            $content = file_get_contents($path);
            if ($content === false) {
                throw new \Exception("Failed to read codeblock include file '$path'");
            }
            
            $madeReplacements = true;
            return $codeblock->withContent($content);
        });

        return $madeReplacements ? $newMarkdown : null;
    }
}