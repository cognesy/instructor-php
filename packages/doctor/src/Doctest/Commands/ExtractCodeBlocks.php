<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Commands;

use Cognesy\Doctor\Doctest\DoctestFile;
use Cognesy\Doctor\Doctest\Internal\MarkdownInfo;
use Cognesy\Doctor\Doctest\Services\DocRepository;
use Cognesy\Doctor\Markdown\MarkdownFile;
use Cognesy\Doctor\Markdown\Nodes\CodeBlockNode;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'extract',
    description: 'Extract code blocks from Markdown files to target directory'
)]
class ExtractCodeBlocks extends Command
{
    public function __construct(
        private DocRepository $docRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addOption(
                'source',
                's',
                InputOption::VALUE_REQUIRED,
                'Source Markdown file path',
            )
            ->addOption(
                'source-dir',
                null,
                InputOption::VALUE_OPTIONAL,
                'Source directory to scan for Markdown files (alternative to --source)',
            )
            ->addOption(
                'target-dir',
                't',
                InputOption::VALUE_OPTIONAL,
                'Target directory to write extracted code files (overrides metadata)',
            )
            ->addOption(
                'extensions',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Comma-separated list of file extensions to process when using --source-dir',
                'md,mdx',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be extracted without actually writing files',
            )
            ->addOption(
                'modify-source',
                'm',
                InputOption::VALUE_NONE,
                'Modify source Markdown files (creates backup with .YYYYMMDD-HHMMSS.bak extension)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        try {
            $sourcePath = $input->getOption('source');
            $sourceDir = $input->getOption('source-dir');
            $targetDir = $input->getOption('target-dir');
            $extensions = $this->parseExtensions($input->getOption('extensions'));
            $isDryRun = $input->getOption('dry-run');
            $modifySource = $input->getOption('modify-source');

            // Validate input
            if (!$sourcePath && !$sourceDir) {
                throw new InvalidArgumentException('Either --source or --source-dir must be specified.');
            }
            if ($sourcePath && $sourceDir) {
                throw new InvalidArgumentException('Cannot specify both --source and --source-dir.');
            }

            // Process files based on input
            if ($sourcePath) {
                return $this->processSingleFile($sourcePath, $targetDir, $isDryRun, $modifySource, $io);
            } else {
                return $this->processDirectory($sourceDir, $targetDir, $extensions, $isDryRun, $modifySource, $io);
            }

        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::INVALID;
        } catch (RuntimeException $e) {
            $io->error("Processing error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function processSingleFile(string $sourcePath, ?string $targetDir, bool $isDryRun, bool $modifySource, SymfonyStyle $io): int {
        $io->section("Processing file: {$sourcePath}");

        $sourceContent = $this->docRepository->readFile($sourcePath);
        $markdown = MarkdownFile::fromString($sourceContent, $sourcePath);

        $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdown));

        if (empty($doctests)) {
            $io->warning('No extractable code blocks found in the file.');
            return Command::SUCCESS;
        }

        $this->displayExtractionPlan($doctests, $targetDir, $io);

        if ($isDryRun) {
            $io->success('Dry run completed. No files were written.');
            return Command::SUCCESS;
        }

        // Create backup if modifying source
        if ($modifySource) {
            $this->createBackup($sourcePath, $io);
        }

        $extracted = 0;
        foreach ($doctests as $doctest) {
            // Extract full code block first
            $outputPath = $this->determineOutputPath($doctest, $targetDir);
            $this->ensureDirectoryExists(dirname($outputPath));

            $this->docRepository->writeFile($outputPath, $doctest->toFileContent());
            $extracted++;

            if ($io->isVerbose()) {
                $io->writeln("  ✓ Extracted {$doctest->id} ({$doctest->language}) to {$outputPath}");
            }

            // Extract individual regions if they exist
            if ($doctest->hasRegions()) {
                $regions = $doctest->getAvailableRegions();
                foreach ($regions as $regionName) {
                    $regionOutputPath = $this->determineRegionOutputPath($doctest, $regionName, $targetDir);
                    $this->ensureDirectoryExists(dirname($regionOutputPath));

                    $this->docRepository->writeFile($regionOutputPath, $doctest->toFileContent($regionName));
                    $extracted++;

                    if ($io->isVerbose()) {
                        $io->writeln("  ✓ Extracted {$doctest->id}:{$regionName} ({$doctest->language}) to {$regionOutputPath}");
                    }
                }
            }
        }

        // Modify source file if requested
        if ($modifySource) {
            $this->modifySourceFile($markdown, $sourcePath, $io);
        }

        $io->success("Successfully extracted {$extracted} code blocks.");
        return Command::SUCCESS;
    }

    private function processDirectory(string $sourceDir, ?string $targetDir, array $extensions, bool $isDryRun, bool $modifySource, SymfonyStyle $io): int {
        $io->section("Processing directory: {$sourceDir}");

        $files = $this->discoverMarkdownFiles($sourceDir, $extensions);

        if (empty($files)) {
            $io->warning("No files found with extensions [" . implode(', ', $extensions) . "] in: {$sourceDir}");
            return Command::SUCCESS;
        }

        $io->writeln("Found " . count($files) . " files to process");

        $totalExtracted = 0;
        $processedFiles = 0;

        foreach ($files as $filePath) {
            $relativePath = str_replace($sourceDir . '/', '', $filePath);

            try {
                $sourceContent = $this->docRepository->readFile($filePath);
                $markdown = MarkdownFile::fromString($sourceContent, $filePath);
                $doctests = iterator_to_array(DoctestFile::fromMarkdown($markdown));

                if (empty($doctests)) {
                    if ($io->isVerbose()) {
                        $io->writeln("  - {$relativePath}: No extractable code blocks");
                    }
                    continue;
                }

                if ($io->isVerbose()) {
                    $io->writeln("  • {$relativePath}: " . count($doctests) . " code blocks");
                }

                if (!$isDryRun) {
                    // Create backup if modifying source
                    if ($modifySource) {
                        $this->createBackup($filePath, $io);
                    }

                    foreach ($doctests as $doctest) {
                        $outputPath = $this->determineOutputPath($doctest, $targetDir);
                        $this->ensureDirectoryExists(dirname($outputPath));
                        $this->docRepository->writeFile($outputPath, $doctest->toFileContent());
                    }

                    // Modify source file if requested
                    if ($modifySource) {
                        $this->modifySourceFile($markdown, $filePath, $io);
                    }
                }

                $totalExtracted += count($doctests);
                $processedFiles++;

            } catch (RuntimeException $e) {
                $io->writeln("  ✗ {$relativePath}: {$e->getMessage()}");
            }
        }

        if ($isDryRun) {
            $io->success("Dry run completed. Would extract {$totalExtracted} code blocks from {$processedFiles} files.");
        } else {
            $io->success("Successfully extracted {$totalExtracted} code blocks from {$processedFiles} files.");
        }

        return Command::SUCCESS;
    }

    private function determineOutputPath(DoctestFile $doctest, ?string $targetDir): string {
        if ($targetDir) {
            // Use provided target directory as root, but preserve the case_dir structure
            $filename = basename($doctest->codePath);
            $caseDir = $doctest->caseDir;

            // Normalize path separators to system ones
            $normalizedTargetDir = $this->normalizePath($targetDir);
            $normalizedCaseDir = $this->normalizePath($caseDir);

            // Combine target dir with case dir and filename
            return rtrim($normalizedTargetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
                ltrim($normalizedCaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
                $filename;
        }

        // Use metadata-based path from Doctest (also normalize it)
        return $this->normalizePath($doctest->codePath);
    }

    private function determineRegionOutputPath(DoctestFile $doctest, string $regionName, ?string $targetDir): string {
        $pathInfo = pathinfo($doctest->codePath);
        $baseFilename = $pathInfo['filename'] ?? '';
        $extension = $pathInfo['extension'] ?? '';

        // Create filename with region suffix: filename_regionName.ext
        $regionFilename = $baseFilename . '_' . $regionName . '.' . $extension;

        if ($targetDir) {
            $normalizedTargetDir = $this->normalizePath($targetDir);
            $normalizedCaseDir = $this->normalizePath($doctest->caseDir);

            return rtrim($normalizedTargetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
                ltrim($normalizedCaseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
                $regionFilename;
        }

        // Use metadata-based path with region filename
        $pathInfo = pathinfo($this->normalizePath($doctest->codePath));
        return $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $regionFilename;
    }

    private function ensureDirectoryExists(string $directory): void {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException("Failed to create directory: {$directory}");
            }
        }
    }

    private function discoverMarkdownFiles(string $directory, array $extensions): array {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                if (in_array($extension, $extensions, true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);
        return $files;
    }

    private function parseExtensions(string $extensionsString): array {
        $extensions = array_map('trim', explode(',', $extensionsString));
        $extensions = array_filter($extensions, fn($ext) => !empty($ext));

        if (empty($extensions)) {
            throw new InvalidArgumentException('At least one file extension must be specified.');
        }

        return $extensions;
    }

    private function displayExtractionPlan(array $doctests, ?string $targetDir, SymfonyStyle $io): void {
        $io->writeln("Found " . count($doctests) . " extractable code blocks:");

        foreach ($doctests as $doctest) {
            $outputPath = $this->determineOutputPath($doctest, $targetDir);
            $io->writeln("  • {$doctest->id} ({$doctest->language}, {$doctest->linesOfCode} lines) → {$outputPath}");
        }
    }

    private function createBackup(string $filePath, SymfonyStyle $io): void {
        $timestamp = date('Ymd-His');
        $backupPath = $filePath . '.' . $timestamp . '.bak';

        $content = $this->docRepository->readFile($filePath);
        $this->docRepository->writeFile($backupPath, $content);

        if ($io->isVerbose()) {
            $io->writeln("  📁 Created backup: {$backupPath}");
        }
    }

    private function modifySourceFile(MarkdownFile $markdown, string $filePath, SymfonyStyle $io): void {
        // Replace extracted code blocks with references or placeholders
        $modifiedMarkdown = $markdown->withReplacedCodeBlocks(function (CodeBlockNode $codeBlock) use ($markdown) {
            $doctest = new DoctestFile(
                id: $codeBlock->id,
                language: $codeBlock->language,
                linesOfCode: $codeBlock->linesOfCode,
                code: $codeBlock->content,
                codePath: '',
                sourceMarkdown: MarkdownInfo::from($markdown),
            );

            // Only modify blocks that would be extracted
            if (empty($doctest->id)
                || !in_array($doctest->language, $doctest->includedTypes)
                || $doctest->linesOfCode < $doctest->minLines
            ) {
                return $codeBlock; // Keep unchanged
            }

            // Generate the relative path to the extracted file
            $extractedPath = $this->generateExtractedFilePath($doctest);

            // Add include metadata to enable GenerateDocs to include the external file
            $updatedMetadata = array_merge($codeBlock->metadata, [
                'include' => $extractedPath,
            ]);

            // Replace with minimal content and include metadata
            return new CodeBlockNode(
                id: $codeBlock->id,
                language: $codeBlock->language,
                content: "// Code extracted - will be included from external file",
                metadata: $updatedMetadata,
                hasPhpOpenTag: $codeBlock->hasPhpOpenTag,
                hasPhpCloseTag: $codeBlock->hasPhpCloseTag,
                originalContent: $codeBlock->originalContent,
            );
        });

        $this->docRepository->writeFile($filePath, $modifiedMarkdown->toString());

        if ($io->isVerbose()) {
            $io->writeln("  ✏️  Modified source file: {$filePath}");
        }
    }

    private function generateExtractedFilePath(DoctestFile $doctest): string {
        // Generate relative path from docs to examples directory
        // This should match the path structure expected by GenerateDocs
        $filename = basename($doctest->codePath);
        $relativePath = $doctest->caseDir . '/' . $filename;

        // Remove leading slashes and normalize path
        return ltrim($relativePath, '/');
    }

    /**
     * Normalize path separators to system-appropriate ones
     */
    private function normalizePath(string $path): string {
        // Replace both forward and backward slashes with system separator
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }
}