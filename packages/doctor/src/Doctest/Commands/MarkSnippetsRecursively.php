<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Commands;

use Cognesy\Doctor\Doctest\Services\DocRepository;
use Cognesy\Doctor\Markdown\MarkdownFile;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'mark-dir',
    description: 'Recursively process Markdown files in a directory and add IDs to code snippets'
)]
class MarkSnippetsRecursively extends Command
{
    public function __construct(
        private DocRepository $docRepository,
    ) {
        parent::__construct();
    }

    #[\Override]
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

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        try {
            $sourceDir = $this->getRequiredOption($input, 'source-dir');
            $sourceBase = realpath($sourceDir) ?: $sourceDir;
            $targetDir = Path::canonicalize($this->getRequiredOption($input, 'target-dir'));
            $extensions = $this->parseExtensions($input->getOption('extensions'));
            $isDryRun = $input->getOption('dry-run');

            // Discover files
            $io->section('Discovering files...');
            $files = $this->discoverFiles($sourceBase, $extensions);

            if (empty($files)) {
                $io->warning("No files found with extensions [" . implode(', ', $extensions) . "] in: {$sourceBase}");
                return Command::SUCCESS;
            }

            $this->displayDiscoveryResultsSimple($files, $extensions, $sourceBase, $io);

            if ($isDryRun) {
                $io->success('Dry run completed. No files were processed.');
                return Command::SUCCESS;
            }

            // Process files
            $io->section('Processing files...');
            [$ok, $failed, $snippetsTotal] = $this->processFiles($files, $sourceBase, $targetDir, $io);

            if ($failed === 0) {
                $io->success([
                    "Successfully processed all {$ok} files.",
                    "Total code snippets processed: {$snippetsTotal}",
                ]);
                return Command::SUCCESS;
            }

            $io->warning([
                "Processed {$ok} files successfully.",
                "Failed to process {$failed} files.",
                "Total code snippets processed: {$snippetsTotal}",
            ]);
            return Command::FAILURE;

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

    private function displayDiscoveryResultsSimple(array $files, array $extensions, string $sourceDir, SymfonyStyle $io): void {
        $io->success('Found ' . count($files) . ' files to process:');
        $byExt = [];
        foreach ($files as $path) {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $byExt[$ext] = ($byExt[$ext] ?? 0) + 1;
        }
        foreach ($byExt as $ext => $count) {
            $io->writeln("  <info>.{$ext}</info>: {$count} files");
        }
        if ($io->isVerbose()) {
            $io->writeln('');
            $io->writeln('Files to process:');
            foreach ($files as $file) {
                $relative = Path::makeRelative($file, $sourceDir);
                $io->writeln("  • {$relative}");
            }
        }
    }

    private function discoverFiles(string $sourceDir, array $extensions): array
    {
        $finder = new Finder();
        $finder->files()->in($sourceDir)->name($this->buildNamePatterns($extensions))->sortByName();
        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getRealPath();
        }
        sort($files);
        return $files;
    }

    private function buildNamePatterns(array $extensions): array
    {
        return array_map(fn($ext) => "*.{$ext}", $extensions);
    }

    private function processFiles(array $files, string $sourceDir, string $targetDir, SymfonyStyle $io): array
    {
        $ok = 0; $failed = 0; $snippets = 0;
        foreach ($files as $filePath) {
            $relativePath = Path::makeRelative($filePath, $sourceDir);
            try {
                $content = $this->docRepository->readFile($filePath);
                $markdown = MarkdownFile::fromString($content, $filePath);
                $snippets += iterator_count($markdown->codeBlocks());

                $targetPath = Path::join($targetDir, $relativePath);
                $this->docRepository->ensureDirectoryExists(dirname($targetPath));
                $this->docRepository->writeFile($targetPath, $markdown->toString());

                if ($io->isVerbose()) {
                    $io->writeln("  ✓ {$relativePath}");
                }
                $ok++;
            } catch (FileNotFoundException $e) {
                $io->writeln("  ✗ {$relativePath}: file not found");
                $failed++;
            } catch (InvalidArgumentException $e) {
                $io->writeln("  ✗ {$relativePath}: invalid argument – " . $e->getMessage());
                $failed++;
            } catch (RuntimeException $e) {
                $io->writeln("  ✗ {$relativePath}: I/O error – " . $e->getMessage());
                $failed++;
            } catch (\Throwable $e) {
                $class = (new \ReflectionClass($e))->getShortName();
                $io->writeln("  ✗ {$relativePath}: {$class} – " . $e->getMessage());
                $failed++;
            }
        }
        return [$ok, $failed, $snippets];
    }
}
