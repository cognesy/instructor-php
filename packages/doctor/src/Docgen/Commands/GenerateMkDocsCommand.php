<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Commands;

use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\MkDocsDocumentation;
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $timeStart = microtime(true);
        $view = new MkDocsGenerationView();
        
        $packagesOnly = $input->getOption('packages-only');
        $examplesOnly = $input->getOption('examples-only');

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

        $this->renderSuccess($result, microtime(true) - $timeStart);
        return Command::SUCCESS;
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

    private function renderSuccess($result, float $totalTime): void {
        Cli::outln(
            sprintf("Done in %.2fs", $totalTime),
            [Color::BOLD, Color::YELLOW],
        );
    }
}