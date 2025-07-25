<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Commands;

use Cognesy\Doctor\Doctest\Data\BatchProcessingResult;
use Cognesy\Doctor\Doctest\Data\FileDiscoveryResult;
use Cognesy\Doctor\Doctest\Services\BatchProcessingService;
use Cognesy\Doctor\Doctest\Services\FileDiscoveryService;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mark-dir',
    description: 'Recursively process Markdown files in a directory and add IDs to code snippets'
)]
class MarkSnippetsRecursively extends Command
{
    public function __construct(
        private FileDiscoveryService $fileDiscoveryService,
        private BatchProcessingService $batchProcessingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void {
        $this
            ->addOption(
                'source-dir',
                's',
                InputOption::VALUE_REQUIRED,
                'Source directory to scan for Markdown files',
            )
            ->addOption(
                'target-dir',
                't',
                InputOption::VALUE_REQUIRED,
                'Target directory to write processed files (preserving directory structure)',
            )
            ->addOption(
                'extensions',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Comma-separated list of file extensions to process',
                'md,mdx',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be processed without actually processing files',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        try {
            $sourceDir = $this->getRequiredOption($input, 'source-dir');
            $targetDir = $this->getRequiredOption($input, 'target-dir');
            $extensions = $this->parseExtensions($input->getOption('extensions'));
            $isDryRun = $input->getOption('dry-run');

            // Discover files
            $io->section('Discovering files...');
            $discoveryResult = $this->fileDiscoveryService->discoverFiles($sourceDir, $extensions);

            if (!$discoveryResult->hasFiles()) {
                $io->warning("No files found with extensions [" . implode(', ', $extensions) . "] in: {$sourceDir}");
                return Command::SUCCESS;
            }

            $this->displayDiscoveryResults($discoveryResult, $io);

            if ($isDryRun) {
                $io->success('Dry run completed. No files were processed.');
                return Command::SUCCESS;
            }

            // Process files
            $io->section('Processing files...');
            $batchResult = $this->batchProcessingService->processFiles($discoveryResult, $targetDir);

            $this->displayProcessingResults($batchResult, $io);

            return $batchResult->isCompletelySuccessful() ? Command::SUCCESS : Command::FAILURE;

        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::INVALID;
        } catch (RuntimeException $e) {
            $io->error("Processing error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function getRequiredOption(InputInterface $input, string $optionName): string {
        $value = $input->getOption($optionName);
        if (empty($value)) {
            throw new InvalidArgumentException("Option --{$optionName} is required.");
        }
        return $value;
    }

    private function parseExtensions(string $extensionsString): array {
        $extensions = array_map('trim', explode(',', $extensionsString));
        $extensions = array_filter($extensions, fn($ext) => !empty($ext));

        if (empty($extensions)) {
            throw new InvalidArgumentException('At least one file extension must be specified.');
        }

        return $extensions;
    }

    private function displayDiscoveryResults(FileDiscoveryResult $result, SymfonyStyle $io): void {
        $io->success("Found {$result->getCount()} files to process:");

        $filesByExtension = $result->getFilesByExtension();
        foreach ($filesByExtension as $extension => $files) {
            $io->writeln("  <info>.{$extension}</info>: " . count($files) . " files");
        }

        if ($io->isVerbose()) {
            $io->writeln('');
            $io->writeln('Files to process:');
            foreach ($result->getFiles() as $file) {
                $io->writeln("  • {$file->relativePath}");
            }
        }
    }

    private function displayProcessingResults(BatchProcessingResult $result, SymfonyStyle $io): void {
        $successCount = $result->getSuccessfulFilesCount();
        $failureCount = $result->getFailedFilesCount();
        $totalFiles = $result->getTotalFilesProcessed();

        if ($result->isCompletelySuccessful()) {
            $io->success([
                "Successfully processed all {$totalFiles} files.",
                "Total code snippets processed: {$result->totalSnippetsProcessed}",
            ]);
        } else {
            $io->warning([
                "Processed {$successCount} of {$totalFiles} files successfully.",
                "Failed to process {$failureCount} files.",
                "Total code snippets processed: {$result->totalSnippetsProcessed}",
            ]);
        }

        // Show successful files in verbose mode
        if ($io->isVerbose() && $result->hasAnySuccess()) {
            $io->writeln('');
            $io->writeln('<info>Successfully processed files:</info>');
            foreach ($result->getSuccessfulResults() as $fileResult) {
                $snippetInfo = $fileResult->snippetsProcessed > 0
                    ? " ({$fileResult->snippetsProcessed} snippets)"
                    : " (no snippets)";
                $io->writeln("  ✓ {$fileResult->getSourceRelativePath()}{$snippetInfo}");
            }
        }

        // Always show failed files
        if ($failureCount > 0) {
            $io->writeln('');
            $io->writeln('<error>Failed to process files:</error>');
            foreach ($result->getFailedResults() as $fileResult) {
                $io->writeln("  ✗ {$fileResult->getSourceRelativePath()}: {$fileResult->error}");
            }
        }
    }
}