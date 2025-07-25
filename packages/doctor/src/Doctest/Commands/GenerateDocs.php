<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Commands;

use Cognesy\Doctor\Docgen\Data\DocumentationConfig;
use Cognesy\Doctor\Docgen\MintlifyDocGenerator;
use Cognesy\Doctor\Docgen\MintlifyDocumentation;
use Cognesy\InstructorHub\Core\Cli;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\Utils\Cli\Color;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[Deprecated('Use GenerateExamplesCommand or GeneratePackagesCommand instead')]
class GenerateDocs extends Command
{
    public function __construct(
        private MintlifyDocGenerator $docGen,
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

    protected function configure(): void {
        $this
            ->setName('gen')
            ->setDescription('Generate all documentation (deprecated - use gen:examples or gen:packages)')
            ->addArgument('refresh', InputArgument::OPTIONAL, 'Use "fresh" to refresh all files')
            ->addOption('examples', 'e', InputOption::VALUE_NONE, 'Generate only examples documentation')
            ->addOption('packages', 'p', InputOption::VALUE_NONE, 'Generate only package documentation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        $examplesOnly = $input->getOption('examples');
        $packagesOnly = $input->getOption('packages');
        $timeStart = microtime(true);

        // Determine which docs to generate
        if ($examplesOnly && $packagesOnly) {
            Cli::outln("Error: Cannot specify both --examples and --packages options.", [Color::BOLD, Color::RED]);
            return Command::FAILURE;
        }

        // Create new domain objects
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
            $result = match(true) {
                $examplesOnly => $documentation->generateExampleDocs(),
                $packagesOnly => $documentation->generatePackageDocs(),
                default => $documentation->generateAll(),
            };

            if ($result->isSuccess()) {
                $time = round($result->duration, 2);
                Cli::outln("Done - in {$time} secs", [Color::BOLD, Color::YELLOW]);
                return Command::SUCCESS;
            } else {
                Cli::outln("Error: " . $result->message, [Color::BOLD, Color::RED]);
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            Cli::outln("Error:", [Color::BOLD, Color::RED]);
            Cli::outln($e->getMessage(), Color::GRAY);
            return Command::FAILURE;
        }
    }
}