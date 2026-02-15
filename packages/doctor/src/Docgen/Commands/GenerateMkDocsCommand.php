<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Commands;

use Cognesy\Doctor\Docgen\Data\DocsConfig;
use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\LlmsDocsGenerator;
use Cognesy\Doctor\Docgen\MkDocsDocumentation;
use Cognesy\Doctor\Docgen\NavigationBuilder;
use Cognesy\Doctor\Docgen\PackageDiscovery;
use Cognesy\Doctor\Docgen\Views\MkDocsGenerationView;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'gen:mkdocs',
    description: 'Generate MkDocs documentation'
)]
class GenerateMkDocsCommand extends Command
{
    public function __construct(
        private ExampleRepository $examples,
        private string $docsSourceDir,
        private string $docsTargetDir,
        private string $cookbookTargetDir,
        private string $codeblocksDir,
        private array $dynamicGroups,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void {
        $this
            ->addOption(
                'packages-only',
                'p',
                InputOption::VALUE_NONE,
                'Generate only package documentation'
            )
            ->addOption(
                'examples-only',
                'e',
                InputOption::VALUE_NONE,
                'Generate only example documentation'
            )
            ->addOption(
                'with-llms',
                'l',
                InputOption::VALUE_NONE,
                'Also generate LLM-friendly documentation (llms.txt, llms-full.txt)'
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $timeStart = microtime(true);
        $view = new MkDocsGenerationView();

        $packagesOnly = (bool) $input->getOption('packages-only');
        $examplesOnly = (bool) $input->getOption('examples-only');
        $withLlms = (bool) $input->getOption('with-llms');

        $view->renderStart();

        // Create domain objects
        $config = DocumentationConfig::create(
            docsSourceDir: $this->docsSourceDir,
            docsTargetDir: $this->docsTargetDir,
            cookbookTargetDir: $this->cookbookTargetDir,
            mintlifySourceIndexFile: '', // Not used for MkDocs
            mintlifyTargetIndexFile: '', // Not used for MkDocs
            codeblocksDir: $this->codeblocksDir,
            dynamicGroups: $this->dynamicGroups,
        );

        $documentation = new MkDocsDocumentation($this->examples, $config);

        if ($packagesOnly) {
            $result = $this->generatePackagesOnly($documentation, $view);
        } elseif ($examplesOnly) {
            $result = $this->generateExamplesOnly($documentation, $view);
        } else {
            $result = $this->generateAll($documentation, $view);
        }

        $view->renderFinalResult($result);

        if (!$result->isSuccess()) {
            return Command::FAILURE;
        }

        // Generate LLM docs if requested
        if ($withLlms) {
            $this->generateLlmsDocs();
        }

        $this->renderSuccess($result, microtime(true) - $timeStart);
        return Command::SUCCESS;
    }

    private function generateLlmsDocs(): void
    {
        $docsConfig = DocsConfig::fromFile();

        if (!$docsConfig->llmsEnabled) {
            return;
        }

        Cli::out('');
        Cli::outln('Generating LLM documentation...', [Color::BOLD, Color::CYAN]);

        $packageDiscovery = new PackageDiscovery(
            packagesDir: 'packages',
            descriptions: $docsConfig->packageDescriptions,
            targetDirs: $docsConfig->packageTargetDirs,
        );

        $navBuilder = new NavigationBuilder(
            config: $docsConfig,
            targetDir: $this->docsTargetDir,
            format: 'mkdocs',
        );

        // Build navigation
        $packages = $packageDiscovery->discover();
        $exampleGroups = $this->examples->getExampleGroups();
        $releaseNotes = $this->scanReleaseNotes();
        $navigation = $navBuilder->buildMkDocsNav($packages, $exampleGroups, $releaseNotes);

        // Generate files
        $generator = new LlmsDocsGenerator(
            projectName: $docsConfig->mainTitle,
            projectDescription: $docsConfig->llmsProjectDescription,
        );

        Cli::out('  Generating ' . $docsConfig->llmsIndexFile . '... ', [Color::DARK_GRAY]);
        $indexPath = $this->docsTargetDir . '/' . $docsConfig->llmsIndexFile;
        $indexResult = $generator->generateIndex($navigation, $indexPath);
        if ($indexResult->isSuccess()) {
            Cli::outln($indexResult->message, [Color::GREEN]);
        } else {
            Cli::outln('failed', [Color::RED]);
        }

        Cli::out('  Generating ' . $docsConfig->llmsFullFile . '... ', [Color::DARK_GRAY]);
        $fullPath = $this->docsTargetDir . '/' . $docsConfig->llmsFullFile;
        $fullResult = $generator->generateFull(
            $navigation,
            $this->docsTargetDir,
            $fullPath,
            $docsConfig->llmsExcludeSections,
        );
        if ($fullResult->isSuccess()) {
            Cli::outln($fullResult->message, [Color::GREEN]);
        } else {
            Cli::outln('failed', [Color::RED]);
        }
    }

    private function scanReleaseNotes(): array
    {
        $releaseNotesDir = $this->docsTargetDir . '/release-notes';
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

    private function generateAll(MkDocsDocumentation $documentation, MkDocsGenerationView $view) {
        // Initialize base files
        $documentation->initializeBaseFiles();
        
        // Show package processing
        $packages = ['instructor', 'polyglot', 'http-client'];
        foreach ($packages as $package) {
            $view->renderPackageProcessing($package);
            $view->renderInlineStart($package);
            $view->renderPackageResult($package, true);
        }

        // Show example processing
        $exampleGroups = $this->examples->getExampleGroups();
        foreach ($exampleGroups as $exampleGroup) {
            foreach ($exampleGroup->examples as $example) {
                if (!empty($example->tab)) {
                    $view->renderExampleProcessing($example);
                    $view->renderExampleResult($example, 'created');
                }
            }
        }

        return $documentation->generateAll();
    }

    private function generatePackagesOnly(MkDocsDocumentation $documentation, MkDocsGenerationView $view) {
        // Initialize base files for standalone execution
        $documentation->initializeBaseFiles();

        // Show individual package processing
        $packages = ['instructor', 'polyglot', 'http-client'];
        foreach ($packages as $package) {
            $view->renderPackageProcessing($package);
            $view->renderInlineStart($package);
            $view->renderPackageResult($package, true);
        }

        $result = $documentation->generatePackageDocs();
        
        // Generate mkdocs.yml config
        $documentation->updateMkDocsConfig();
        
        return $result;
    }

    private function generateExamplesOnly(MkDocsDocumentation $documentation, MkDocsGenerationView $view) {
        // Initialize base files for standalone execution
        $documentation->initializeBaseFiles();

        // Show individual example processing
        $exampleGroups = $this->examples->getExampleGroups();
        foreach ($exampleGroups as $exampleGroup) {
            foreach ($exampleGroup->examples as $example) {
                if (!empty($example->tab)) {
                    $view->renderExampleProcessing($example);
                    $view->renderExampleResult($example, 'created');
                }
            }
        }

        $result = $documentation->generateExampleDocs();
        
        // Generate mkdocs.yml config
        $documentation->updateMkDocsConfig();
        
        return $result;
    }

    private function renderSuccess(mixed $result, float $totalTime): void {
        Cli::outln(
            sprintf("Done in %.2fs", $totalTime),
            [Color::BOLD, Color::YELLOW],
        );
    }
}