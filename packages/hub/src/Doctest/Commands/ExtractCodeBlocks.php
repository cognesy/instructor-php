<?php declare(strict_types=1);

namespace Cognesy\InstructorHub\Doctest\Commands;

use Cognesy\InstructorHub\Doctest\Doctest;
use Cognesy\InstructorHub\Doctest\DocRepo\DocRepository;
use Cognesy\InstructorHub\Markdown\MarkdownFile;
use Cognesy\InstructorHub\Markdown\Nodes\CodeBlockNode;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'docs:extract',
    description: 'Extract code blocks from Markdown files to target directory'
)]
class ExtractCodeBlocks extends Command
{
    public function __construct(
        private DocRepository $docRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'source',
                's',
                InputOption::VALUE_REQUIRED,
                'Source Markdown file path'
            )
            ->addOption(
                'source-dir',
                null,
                InputOption::VALUE_OPTIONAL,
                'Source directory to scan for Markdown files (alternative to --source)'
            )
            ->addOption(
                'target-dir',
                't',
                InputOption::VALUE_OPTIONAL,
                'Target directory to write extracted code files (overrides metadata)'
            )
            ->addOption(
                'extensions',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Comma-separated list of file extensions to process when using --source-dir',
                'md,mdx'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be extracted without actually writing files'
            )
            ->addOption(
                'modify-source',
                'm',
                InputOption::VALUE_NONE,
                'Modify source Markdown files (creates backup with .YYYYMMDD-HHMMSS.bak extension)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
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

    private function processSingleFile(string $sourcePath, ?string $targetDir, bool $isDryRun, bool $modifySource, SymfonyStyle $io): int
    {
        $io->section("Processing file: {$sourcePath}");

        $sourceContent = $this->docRepository->readFile($sourcePath);
        $markdown = MarkdownFile::fromString($sourceContent, $sourcePath);

        $doctests = iterator_to_array(Doctest::fromMarkdown($markdown));

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
            $outputPath = $this->determineOutputPath($doctest, $targetDir);
            $this->ensureDirectoryExists(dirname($outputPath));
            
            $this->docRepository->writeFile($outputPath, $doctest->toFileContent());
            $extracted++;
            
            if ($io->isVerbose()) {
                $io->writeln("  ✓ Extracted {$doctest->id} ({$doctest->language}) to {$outputPath}");
            }
        }

        // Modify source file if requested
        if ($modifySource) {
            $this->modifySourceFile($markdown, $sourcePath, $io);
        }

        $io->success("Successfully extracted {$extracted} code blocks.");
        return Command::SUCCESS;
    }

    private function processDirectory(string $sourceDir, ?string $targetDir, array $extensions, bool $isDryRun, bool $modifySource, SymfonyStyle $io): int
    {
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
                $doctests = iterator_to_array(Doctest::fromMarkdown($markdown));

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

    private function determineOutputPath(Doctest $doctest, ?string $targetDir): string
    {
        if ($targetDir) {
            // Use provided target directory with original filename structure
            $filename = basename($doctest->codePath);
            return rtrim($targetDir, '/') . '/' . $filename;
        }
        
        // Use metadata-based path from Doctest
        return $doctest->codePath;
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException("Failed to create directory: {$directory}");
            }
        }
    }

    private function discoverMarkdownFiles(string $directory, array $extensions): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
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

    private function parseExtensions(string $extensionsString): array
    {
        $extensions = array_map('trim', explode(',', $extensionsString));
        $extensions = array_filter($extensions, fn($ext) => !empty($ext));

        if (empty($extensions)) {
            throw new InvalidArgumentException('At least one file extension must be specified.');
        }

        return $extensions;
    }

    private function displayExtractionPlan(array $doctests, ?string $targetDir, SymfonyStyle $io): void
    {
        $io->writeln("Found " . count($doctests) . " extractable code blocks:");

        foreach ($doctests as $doctest) {
            $outputPath = $this->determineOutputPath($doctest, $targetDir);
            $io->writeln("  • {$doctest->id} ({$doctest->language}, {$doctest->linesOfCode} lines) → {$outputPath}");
        }
    }

    private function createBackup(string $filePath, SymfonyStyle $io): void
    {
        $timestamp = date('Ymd-His');
        $backupPath = $filePath . '.' . $timestamp . '.bak';
        
        $content = $this->docRepository->readFile($filePath);
        $this->docRepository->writeFile($backupPath, $content);
        
        if ($io->isVerbose()) {
            $io->writeln("  📁 Created backup: {$backupPath}");
        }
    }

    private function modifySourceFile(MarkdownFile $markdown, string $filePath, SymfonyStyle $io): void
    {
        // Replace extracted code blocks with references or placeholders
        $modifiedMarkdown = $markdown->withReplacedCodeBlocks(function (CodeBlockNode $codeBlock) {
            // Only modify blocks that would be extracted (have ID and meet criteria)
            if (empty($codeBlock->metadata['id'] ?? '')) {
                return $codeBlock; // Keep unchanged
            }
            
            $id = $codeBlock->metadata['id'];
            $language = $codeBlock->language;
            
            // Replace with a reference comment
            return new CodeBlockNode(
                language: $language,
                content: "// Code extracted to external file - see doctest ID: {$id}",
                metadata: $codeBlock->metadata
            );
        });
        
        $this->docRepository->writeFile($filePath, $modifiedMarkdown->toString());
        
        if ($io->isVerbose()) {
            $io->writeln("  ✏️  Modified source file: {$filePath}");
        }
    }
}