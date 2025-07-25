<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Commands;

use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\MintlifyDocumentation;
use Cognesy\Doctor\Docgen\Views\PackageGenerationView;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GeneratePackagesCommand extends Command
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

    protected function configure(): void
    {
        $this
            ->setName('gen:packages')
            ->setDescription('Generate package documentation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeStart = microtime(true);
        $view = new PackageGenerationView();
        
        $view->renderStart();
        
        // Create domain objects
        $config = DocumentationConfig::create(
            docsSourceDir: $this->docsSourceDir,
            docsTargetDir: $this->docsTargetDir,
            cookbookTargetDir: $this->cookbookTargetDir,
            mintlifySourceIndexFile: $this->mintlifySourceIndexFile,
            mintlifyTargetIndexFile: $this->mintlifyTargetIndexFile,
            codeblocksDir: $this->codeblocksDir,
            dynamicGroups: $this->dynamicGroups
        );
        
        $documentation = new MintlifyDocumentation($this->examples, $config);

        try {
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
            
            if ($result->isSuccess()) {
                $view->renderFinalResult($result);
                $this->renderSuccess($result, microtime(true) - $timeStart);
                return Command::SUCCESS;
            } else {
                $view->renderFinalResult($result);
                return Command::FAILURE;
            }

        } catch (\Throwable $e) {
            Cli::outln("Fatal error: " . $e->getMessage(), [Color::BOLD, Color::RED]);
            return Command::FAILURE;
        }
    }

    private function renderSuccess($result, float $totalTime): void
    {
        Cli::outln(
            sprintf("Done in %.2fs", $totalTime),
            [Color::BOLD, Color::YELLOW]
        );
    }
}