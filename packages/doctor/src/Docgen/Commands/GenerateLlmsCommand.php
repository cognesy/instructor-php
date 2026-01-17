<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Commands;

use Cognesy\Config\BasePath;
use Cognesy\Doctor\Docgen\Data\DocsConfig;
use Cognesy\Doctor\Docgen\LlmsDocsGenerator;
use Cognesy\Doctor\Docgen\NavigationBuilder;
use Cognesy\Doctor\Docgen\PackageDiscovery;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Cognesy\Utils\Files;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'gen:llms',
    description: 'Generate LLM-friendly documentation (llms.txt, llms-full.txt)'
)]
class GenerateLlmsCommand extends Command
{
    private ?DocsConfig $config = null;
    private ?PackageDiscovery $packageDiscovery = null;
    private ?NavigationBuilder $navBuilder = null;

    public function __construct(
        private ExampleRepository $examples,
        private string $mkdocsTargetDir,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                'deploy',
                'd',
                InputOption::VALUE_NONE,
                'Deploy generated files to website'
            )
            ->addOption(
                'target',
                't',
                InputOption::VALUE_REQUIRED,
                'Custom deployment target path (overrides config)'
            )
            ->addOption(
                'index-only',
                'i',
                InputOption::VALUE_NONE,
                'Generate only llms.txt index file'
            )
            ->addOption(
                'full-only',
                'f',
                InputOption::VALUE_NONE,
                'Generate only llms-full.txt file'
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeStart = microtime(true);

        // Load configuration
        $this->config = DocsConfig::fromFile();

        if (!$this->config->llmsEnabled) {
            Cli::outln('LLM documentation generation is disabled in config', [Color::YELLOW]);
            return Command::SUCCESS;
        }

        // Initialize services
        $this->initializeServices();

        $deploy = (bool) $input->getOption('deploy');
        $customTarget = $input->getOption('target');
        $indexOnly = (bool) $input->getOption('index-only');
        $fullOnly = (bool) $input->getOption('full-only');

        Cli::outln('Generating LLM documentation...', [Color::BOLD, Color::CYAN]);
        Cli::out('');

        // Build navigation from MkDocs structure
        Cli::out('  Scanning navigation structure... ', [Color::DARK_GRAY]);
        $navigation = $this->buildNavigation();
        Cli::outln('done', [Color::GREEN]);

        // Create generator
        $generator = new LlmsDocsGenerator(
            projectName: $this->config->mainTitle,
            projectDescription: $this->config->llmsProjectDescription,
        );

        $hasErrors = false;

        // Generate llms.txt
        if (!$fullOnly) {
            Cli::out('  Generating ' . $this->config->llmsIndexFile . '... ', [Color::DARK_GRAY]);
            $indexPath = $this->mkdocsTargetDir . '/' . $this->config->llmsIndexFile;
            $indexResult = $generator->generateIndex($navigation, $indexPath);

            if ($indexResult->isSuccess()) {
                Cli::outln($indexResult->message, [Color::GREEN]);
            } else {
                Cli::outln('failed: ' . implode(', ', $indexResult->errors), [Color::RED]);
                $hasErrors = true;
            }
        }

        // Generate llms-full.txt
        if (!$indexOnly) {
            Cli::out('  Generating ' . $this->config->llmsFullFile . '... ', [Color::DARK_GRAY]);
            $fullPath = $this->mkdocsTargetDir . '/' . $this->config->llmsFullFile;
            $fullResult = $generator->generateFull(
                $navigation,
                $this->mkdocsTargetDir,
                $fullPath,
                $this->config->llmsExcludeSections,
            );

            if ($fullResult->isSuccess()) {
                Cli::outln($fullResult->message, [Color::GREEN]);
            } else {
                Cli::outln('failed: ' . implode(', ', $fullResult->errors), [Color::RED]);
                $hasErrors = true;
            }
        }

        // Deploy if requested
        if ($deploy && !$hasErrors && $this->config !== null) {
            $deployTarget = is_string($customTarget) ? $customTarget : $this->config->llmsDeployTarget;

            if (empty($deployTarget)) {
                Cli::outln('');
                Cli::outln('  No deployment target configured. Use --target or set llms.deploy.target in config.', [Color::YELLOW]);
            } else {
                $this->deployToWebsite($deployTarget, $indexOnly, $fullOnly);
            }
        }

        Cli::out('');
        $totalTime = microtime(true) - $timeStart;
        Cli::outln(sprintf('Done in %.2fs', $totalTime), [Color::BOLD, Color::YELLOW]);

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    private function initializeServices(): void
    {
        $this->packageDiscovery = new PackageDiscovery(
            packagesDir: 'packages',
            descriptions: $this->config->packageDescriptions,
            targetDirs: $this->config->packageTargetDirs,
        );

        $this->navBuilder = new NavigationBuilder(
            config: $this->config,
            targetDir: $this->mkdocsTargetDir,
            format: 'mkdocs',
        );
    }

    private function buildNavigation(): array
    {
        $packages = $this->packageDiscovery->discover();
        $exampleGroups = $this->examples->getExampleGroups();
        $releaseNotes = $this->scanReleaseNotes();

        return $this->navBuilder->buildMkDocsNav($packages, $exampleGroups, $releaseNotes);
    }

    private function scanReleaseNotes(): array
    {
        $releaseNotesDir = $this->mkdocsTargetDir . '/release-notes';
        if (!is_dir($releaseNotesDir)) {
            return [];
        }

        $files = glob($releaseNotesDir . '/*.md') ?: [];
        $versions = [];

        foreach ($files as $file) {
            $filename = basename($file, '.md');
            if ($filename === 'versions') {
                continue;
            }

            if (preg_match('/^v(.+)$/', $filename, $matches)) {
                $versions[] = [
                    'version' => $matches[1],
                    'filename' => $filename,
                    'path' => 'release-notes/' . $filename . '.md'
                ];
            }
        }

        usort($versions, fn($a, $b) => version_compare($b['version'], $a['version']));

        return $versions;
    }

    private function deployToWebsite(string $targetPath, bool $indexOnly, bool $fullOnly): void
    {
        Cli::out('');
        Cli::outln('  Deploying to website...', [Color::CYAN]);

        $targetPath = BasePath::get($targetPath);

        if (!is_dir($targetPath)) {
            Cli::outln("    Target directory does not exist: {$targetPath}", [Color::RED]);
            Cli::outln("    Create it first or check your config.", [Color::YELLOW]);
            return;
        }

        $filesDeployed = 0;

        // Deploy llms.txt to website root
        if (!$fullOnly) {
            $source = $this->mkdocsTargetDir . '/' . $this->config->llmsIndexFile;
            $dest = $targetPath . '/' . $this->config->llmsIndexFile;

            if (file_exists($source)) {
                copy($source, $dest);
                Cli::outln("    → {$dest}", [Color::GREEN]);
                $filesDeployed++;
            }
        }

        // Deploy llms-full.txt to website root
        if (!$indexOnly) {
            $source = $this->mkdocsTargetDir . '/' . $this->config->llmsFullFile;
            $dest = $targetPath . '/' . $this->config->llmsFullFile;

            if (file_exists($source)) {
                copy($source, $dest);
                Cli::outln("    → {$dest}", [Color::GREEN]);
                $filesDeployed++;
            }
        }

        // Optionally deploy full docs folder
        if (!$indexOnly && !$fullOnly && !empty($this->config->llmsDeployDocsFolder)) {
            $docsTarget = $targetPath . '/' . $this->config->llmsDeployDocsFolder;

            // Count files to deploy
            $mdFiles = $this->countMarkdownFiles($this->mkdocsTargetDir);

            // Copy docs (excluding llms.txt files)
            $this->copyDocsFolder($this->mkdocsTargetDir, $docsTarget);

            Cli::outln("    → {$docsTarget}/ ({$mdFiles} files)", [Color::GREEN]);
        }

        Cli::outln("  Deployed {$filesDeployed} files to website root", [Color::CYAN]);
    }

    private function countMarkdownFiles(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $count++;
            }
        }

        return $count;
    }

    private function copyDocsFolder(string $source, string $target): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), strlen($source) + 1);

            // Skip llms.txt and llms-full.txt
            if (in_array(basename($relativePath), [$this->config->llmsIndexFile, $this->config->llmsFullFile])) {
                continue;
            }

            $targetPath = $target . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $targetPath);
            }
        }
    }
}
