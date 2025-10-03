<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Commands;

use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\MintlifyDocumentation;
use Cognesy\Doctor\Docgen\Views\PackageGenerationView;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'gen:mintlify',
    description: 'Generate Mintlify documentation'
)]
class GenerateMintlifyCommand extends Command
{
    public function __construct(
        private ExampleRepository $examples,
        private string $docsSourceDir,
        private string $docsTargetDir,
        private string $cookbookTargetDir,
        private string $mintlifySourceIndexFile,
        private string $mintlifyTargetIndexFile,
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
            );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $timeStart = microtime(true);
        $view = new PackageGenerationView();
        
        $packagesOnly = $input->getOption('packages-only');
        $examplesOnly = $input->getOption('examples-only');

        Cli::outln("Generating Mintlify documentation...", [Color::BOLD, Color::CYAN]);

        // Create domain objects
        $config = DocumentationConfig::create(
            docsSourceDir: $this->docsSourceDir,
            docsTargetDir: $this->docsTargetDir,
            cookbookTargetDir: $this->cookbookTargetDir,
            mintlifySourceIndexFile: $this->mintlifySourceIndexFile,
            mintlifyTargetIndexFile: $this->mintlifyTargetIndexFile,
            codeblocksDir: $this->codeblocksDir,
            dynamicGroups: $this->dynamicGroups,
        );

        $documentation = new MintlifyDocumentation($this->examples, $config);

        if ($packagesOnly) {
            $result = $this->generatePackagesOnly($documentation, $view);
        } elseif (!empty($examplesOnly)) {
            $result = $this->generateExamplesOnly($documentation, $view);
        } else {
            $result = $this->generateAll($documentation, $view);
        }
        
        if (!$result->isSuccess()) {
            return Command::FAILURE;
        }

        $this->renderSuccess($result, microtime(true) - $timeStart);
        return Command::SUCCESS;
    }

    private function generateAll(MintlifyDocumentation $documentation, PackageGenerationView $view) {
        // Initialize base files
        $documentation->initializeBaseFiles();
        
        // Show package processing
        $packages = ['instructor', 'polyglot', 'http-client'];
        foreach ($packages as $package) {
            $view->renderPackageProcessing($package);
            $view->renderInlineStart($package);
            $view->renderPackageResult($package, true);
        }

        // Generate packages
        $packageResult = $documentation->generatePackageDocs();
        
        if (!$packageResult->isSuccess()) {
            return $packageResult;
        }

        // Show example processing
        $exampleGroups = $this->examples->getExampleGroups();
        foreach ($exampleGroups as $exampleGroup) {
            foreach ($exampleGroup->examples as $example) {
                if (!empty($example->tab)) {
                    Cli::out(" [.] ");
                    Cli::out($example->name, [Color::BOLD, Color::WHITE]);
                    Cli::out(" > ");
                    Cli::out("new example", [Color::YELLOW]);
                    Cli::out(" > ");
                    Cli::outln("CREATED", [Color::BOLD, Color::GREEN]);
                }
            }
        }

        // Generate examples
        $exampleResult = $documentation->generateExampleDocs();
        
        if (!$exampleResult->isSuccess()) {
            return $exampleResult;
        }

        // Combine results
        return $packageResult;
    }

    private function generatePackagesOnly(MintlifyDocumentation $documentation, PackageGenerationView $view) {
        // Show individual package processing
        $packages = ['instructor', 'polyglot', 'http-client'];
        foreach ($packages as $package) {
            $view->renderPackageProcessing($package);
            $view->renderInlineStart($package);
            $view->renderPackageResult($package, true);
        }

        return $documentation->generatePackageDocs();
    }

    private function generateExamplesOnly(MintlifyDocumentation $documentation, PackageGenerationView $view) {
        // Show individual example processing
        $exampleGroups = $this->examples->getExampleGroups();
        foreach ($exampleGroups as $exampleGroup) {
            foreach ($exampleGroup->examples as $example) {
                if (!empty($example->tab)) {
                    Cli::out(" [.] ");
                    Cli::out($example->name, [Color::BOLD, Color::WHITE]);
                    Cli::out(" > ");
                    Cli::out("new example", [Color::YELLOW]);
                    Cli::out(" > ");
                    Cli::outln("CREATED", [Color::BOLD, Color::GREEN]);
                }
            }
        }

        return $documentation->generateExampleDocs();
    }

    private function renderSuccess(mixed $result, float $totalTime): void {
        Cli::outln("✓ Mintlify Documentation Generation Complete", [Color::BOLD, Color::GREEN]);
        Cli::outln(
            sprintf("Done in %.2fs", $totalTime),
            [Color::BOLD, Color::YELLOW],
        );
    }
}