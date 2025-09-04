<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Commands;

use Cognesy\Doctor\Doctest\Data\ValidationResult;
use Cognesy\Doctor\Doctest\Events\FileValidated;
use Cognesy\Doctor\Doctest\Events\ValidationCompleted;
use Cognesy\Doctor\Doctest\Events\ValidationStarted;
use Cognesy\Doctor\Doctest\Listeners\ValidationMetricsCollector;
use Cognesy\Doctor\Doctest\Services\ValidationService;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Utils\Files;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'validate',
    description: 'Validate extracted code blocks and list missing/wrong paths'
)]
class ValidateCodeBlocks extends Command
{
    private ValidationMetricsCollector $metricsCollector;

    private EventDispatcher $eventDispatcher;

    public function __construct(?EventDispatcher $eventDispatcher = null) {
        parent::__construct();
        
        // Create or use injected event dispatcher and metrics collector
        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher();
        $this->metricsCollector = new ValidationMetricsCollector();
        
        // Register listeners for each event type
        $this->eventDispatcher->addListener(
            ValidationStarted::class,
            fn($event) => $this->metricsCollector->handle($event)
        );
        $this->eventDispatcher->addListener(
            FileValidated::class,
            fn($event) => $this->metricsCollector->handle($event)
        );
        $this->eventDispatcher->addListener(
            ValidationCompleted::class,
            fn($event) => $this->metricsCollector->handle($event)
        );
    }

    protected function configure(): void {
        $this
            ->addOption(
                'source',
                's',
                InputOption::VALUE_REQUIRED,
                'Source Markdown file path to validate',
            )
            ->addOption(
                'source-dir',
                null,
                InputOption::VALUE_OPTIONAL,
                'Source directory to scan for Markdown files (alternative to --source)',
            )
            ->addOption(
                'extensions',
                'e',
                InputOption::VALUE_OPTIONAL,
                'Comma-separated list of file extensions to process when using --source-dir',
                'md,mdx',
            )
            ->addOption(
                'show-all',
                'a',
                InputOption::VALUE_NONE,
                'Show all code blocks (both valid and invalid paths)',
            )
            ->addOption(
                'show-progress',
                'p',
                InputOption::VALUE_NONE,
                'Show detailed processing information for directories and files',
            )
            ->addOption(
                'show-paths',
                null,
                InputOption::VALUE_NONE,
                'Print resolved expected paths for each validated code block',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        try {
            $sourcePath = $input->getOption('source');
            $sourceDir = $input->getOption('source-dir');
            $extensions = $this->parseExtensions($input->getOption('extensions'));
            $showAll = $input->getOption('show-all');
            $verbose = $input->getOption('show-progress');
            $showPaths = $input->getOption('show-paths');

            // Validate input
            if (!$sourcePath && !$sourceDir) {
                throw new InvalidArgumentException('Either --source or --source-dir must be specified.');
            }
            if ($sourcePath && $sourceDir) {
                throw new InvalidArgumentException('Cannot specify both --source and --source-dir.');
            }

            // Process files based on input
            if ($sourcePath) {
                $this->eventDispatcher->dispatch(new ValidationStarted($sourcePath));
                if ($verbose) {
                    $io->writeln("Processing file: {$sourcePath}");
                }
                $result = $this->processFileWithTiming($sourcePath);
                $results = [$result];
                if ($verbose && $result->totalBlocks === 0) {
                    $io->writeln("  No extracted code blocks found");
                }
                if ($showPaths) {
                    $this->printBlockDetails($result, $io);
                }
                $this->eventDispatcher->dispatch(new ValidationCompleted($results));
            } else {
                $this->eventDispatcher->dispatch(new ValidationStarted($sourceDir));
                $results = $this->processDirectory($sourceDir, $extensions, $verbose, $showPaths, $io);
                $this->eventDispatcher->dispatch(new ValidationCompleted($results));
            }

            // Display results
            $this->displayResults($results, $showAll, $io);
            
            // Display summary
            $io->writeln('');
            $io->writeln($this->metricsCollector->formatSummary());

            return $this->hasErrors($results) ? Command::FAILURE : Command::SUCCESS;

        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());
            return Command::INVALID;
        } catch (RuntimeException $e) {
            $io->error("Validation error: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    private function displayResults(array $results, bool $showAll, SymfonyStyle $io): void
    {
        if (empty($results)) {
            return;
        }

        foreach ($results as $result) {
            // Only display files that have blocks to show
            if ($result->totalBlocks === 0) {
                continue;
            }

            if (!empty($result->missingBlocks)) {
                $io->writeln("<fg=red>❌</> {$result->filePath}");
                foreach ($result->missingBlocks as $missing) {
                    $lineInfo = $missing->lineNumber ? ":{$missing->lineNumber}" : '';
                    $io->writeln("  {$lineInfo} {$missing->id} → {$missing->expectedPath}");
                }
            }

            if ($showAll && !empty($result->validBlocks)) {
                $io->writeln("<fg=green>✅</> {$result->filePath}");
                foreach ($result->validBlocks as $valid) {
                    $lineInfo = $valid->lineNumber ? ":{$valid->lineNumber}" : '';
                    $io->writeln("  {$lineInfo} {$valid->id} → {$valid->expectedPath}");
                }
            }
        }
    }

    private function processDirectory(string $sourceDir, array $extensions, bool $verbose, bool $showPaths, SymfonyStyle $io): array
    {
        if ($verbose) {
            $io->writeln("Scanning directory: {$sourceDir}");
        }

        // Discover files using Files utility
        $matchingFiles = [];
        foreach (Files::files($sourceDir) as $fileInfo) {
            $extension = strtolower($fileInfo->getExtension());
            if (in_array($extension, $extensions, true)) {
                $matchingFiles[] = $fileInfo->getPathname();
            }
        }

        sort($matchingFiles);

        if ($verbose) {
            $io->writeln("Found " . count($matchingFiles) . " files with extensions [" . implode(', ', $extensions) . "]");
        }

        $results = [];
        foreach ($matchingFiles as $filePath) {
            $relativePath = \Symfony\Component\Filesystem\Path::makeRelative($filePath, $sourceDir);

            if ($verbose) {
                $io->writeln("  Processing: {$relativePath}");
            }

            try {
                $result = $this->processFileWithTiming($filePath);
                // Always add result to track all processed files, even those with 0 blocks
                $results[] = $result;
                
                if ($result->totalBlocks > 0) {
                    if ($verbose) {
                        $validCount = $result->validCount();
                        $missingCount = $result->missingCount();
                        $status = $missingCount > 0 ? "<fg=red>{$missingCount} missing</>" : "<fg=green>all valid</>";
                        $io->writeln("    → {$result->totalBlocks} blocks, {$status}");
                    }
                    if ($verbose || $showPaths) {
                        $this->printBlockDetails($result, $io, indent: '    ');
                    }
                } else {
                    if ($verbose) {
                        $io->writeln("    → No extracted code blocks");
                    }
                }
            } catch (RuntimeException $e) {
                if ($verbose) {
                    $io->writeln("    → <fg=red>Error:</> {$e->getMessage()}");
                }
            }
        }

        return $results;
    }

    private function printBlockDetails(ValidationResult $result, SymfonyStyle $io, string $indent = ''): void
    {
        foreach ($result->validBlocks as $valid) {
            $lineInfo = $valid->lineNumber ? ":{$valid->lineNumber}" : '';
            $io->writeln(sprintf(
                "%s  <fg=green>✔</> %s%s %s → %s",
                $indent,
                $result->filePath,
                $lineInfo,
                $valid->id ? " [{$valid->id}]" : '',
                $valid->expectedPath
            ));
        }
        foreach ($result->missingBlocks as $missing) {
            $lineInfo = $missing->lineNumber ? ":{$missing->lineNumber}" : '';
            $io->writeln(sprintf(
                "%s  <fg=red>✖</> %s%s %s → %s",
                $indent,
                $result->filePath,
                $lineInfo,
                $missing->id ? " [{$missing->id}]" : '',
                $missing->expectedPath
            ));
        }
    }

    private function processFileWithTiming(string $filePath): ValidationResult
    {
        $startTime = microtime(true);
        $validationService = new ValidationService();
        $result = $validationService->validateFile($filePath);
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Create a new result with the correct timing
        $timedResult = new ValidationResult(
            filePath: $result->filePath,
            totalBlocks: $result->totalBlocks,
            validBlocks: $result->validBlocks,
            missingBlocks: $result->missingBlocks,
            duration: $duration,
        );
        
        // Dispatch the FileValidated event
        $this->eventDispatcher->dispatch(new FileValidated($timedResult));
        
        return $timedResult;
    }

    private function hasErrors(array $results): bool
    {
        foreach ($results as $result) {
            if ($result->hasErrors()) {
                return true;
            }
        }
        return false;
    }


    private function parseExtensions(string $extensionsString): array {
        $extensions = array_map('trim', explode(',', $extensionsString));
        $extensions = array_filter($extensions, fn($ext) => !empty($ext));

        if (empty($extensions)) {
            throw new InvalidArgumentException('At least one file extension must be specified.');
        }

        return $extensions;
    }
}
