<?php declare(strict_types=1);

namespace Cognesy\Doctor\Docgen\Commands;

use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\Data\FileProcessingResult;
use Cognesy\Doctor\Docgen\MintlifyDocumentation;
use Cognesy\Doctor\Docgen\Views\ExampleGenerationView;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateExamplesCommand extends Command
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
            ->setName('gen:examples')
            ->setDescription('Generate example documentation');
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int {
        $timeStart = microtime(true);
        $view = new ExampleGenerationView();

        $view->renderStart();

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

        try {
            // Show individual example processing
            $exampleGroups = $this->examples->getExampleGroups();
            foreach ($exampleGroups as $exampleGroup) {
                foreach ($exampleGroup->examples as $example) {
                    if (!empty($example->tab)) {
                        $view->renderExampleProcessing($example);
                        // Simulate individual processing result
                        $view->renderFileResult(FileProcessingResult::created($example->name));
                    }
                }
            }

            $result = $documentation->generateExampleDocs();

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

    private function renderSuccess(mixed $result, float $totalTime): void {
        Cli::outln(
            sprintf("Done in %.2fs", $totalTime),
            [Color::BOLD, Color::YELLOW],
        );
    }
}