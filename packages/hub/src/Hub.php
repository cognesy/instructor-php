<?php declare(strict_types=1);

namespace Cognesy\InstructorHub;

use Cognesy\Config\BasePath;
use Cognesy\InstructorHub\Commands\ClearDocs;
use Cognesy\InstructorHub\Commands\GenerateDocs;
use Cognesy\InstructorHub\Commands\ListAllExamples;
use Cognesy\InstructorHub\Commands\RunAllExamples;
use Cognesy\InstructorHub\Commands\RunOneExample;
use Cognesy\InstructorHub\Commands\ShowExample;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\MintlifyDocGenerator;
use Cognesy\InstructorHub\Services\Runner;
use Cognesy\InstructorHub\Doctest\Batch\BatchProcessingService;
use Cognesy\InstructorHub\Doctest\Commands\ExtractCodeBlocks;
use Cognesy\InstructorHub\Doctest\Commands\MarkSnippets;
use Cognesy\InstructorHub\Doctest\Commands\MarkSnippetsRecursively;
use Cognesy\InstructorHub\Doctest\DocRepo\DocRepository;
use Cognesy\InstructorHub\Doctest\FileDiscovery\FileDiscoveryService;
use Symfony\Component\Console\Application;
use Symfony\Component\Filesystem\Filesystem;

class Hub extends Application
{
    private ExampleRepository $exampleRepo;
    private MintlifyDocGenerator $docGen;
    private Runner $runner;
    private Filesystem $filesystem;
    private DocRepository $docRepository;
    private $fileDiscoveryService;
    private $batchProcessingService;

    public function __construct() {
        parent::__construct('Hub // Instructor for PHP', '1.0.0');
        //$this->setDescription('(^) Get typed structured outputs from LLMs');

        $this->registerServices();
        $this->registerCommands();
    }

    private function registerServices(): void
    {
        $this->exampleRepo = new ExampleRepository(
            BasePath::get('examples'),
        );
        $this->docGen = new MintlifyDocGenerator(
            examples: $this->exampleRepo,
            docsSourceDir: BasePath::get('docs'),
            docsTargetDir: BasePath::get('docs-build'),
            cookbookTargetDir: BasePath::get('docs-build/cookbook'),
            mintlifySourceIndexFile: BasePath::get('docs/mint.json'),
            mintlifyTargetIndexFile: BasePath::get('docs-build/mint.json'),
            dynamicGroups: [
                'Cookbook \ Instructor \ Basics',
                'Cookbook \ Instructor \ Advanced',
                'Cookbook \ Instructor \ Prompting',
                'Cookbook \ Instructor \ Troubleshooting',
                'Cookbook \ Instructor \ API Support',

                "Cookbook \ Polyglot \ LLM Basics",
                "Cookbook \ Polyglot \ LLM Advanced",
                "Cookbook \ Polyglot \ LLM Troubleshooting",
                "Cookbook \ Polyglot \ LLM API Support",
                "Cookbook \ Polyglot \ LLM Extras",

                'Cookbook \ Prompting \ Extras',
                'Cookbook \ Prompting \ Zero-Shot Prompting',
                'Cookbook \ Prompting \ Few-Shot Prompting',
                'Cookbook \ Prompting \ Thought Generation',
                'Cookbook \ Prompting \ Ensembling',
                'Cookbook \ Prompting \ Self-Criticism',
                'Cookbook \ Prompting \ Decomposition',
                'Cookbook \ Prompting \ Miscellaneous',
            ],
        );
        $this->runner = new Runner(
            examples: $this->exampleRepo,
            displayErrors: true,
            stopAfter: 0,
            stopOnError: false,
        );

        $this->filesystem = new Filesystem();
        $this->docRepository = new DocRepository($this->filesystem);
        $this->fileDiscoveryService = new FileDiscoveryService();
        $this->batchProcessingService = new BatchProcessingService(
            $this->docRepository,
        );
    }

    private function registerCommands(): void
    {
        // Register commands
        $this->addCommands([
            new GenerateDocs($this->docGen),
            new ClearDocs($this->docGen),
            new ListAllExamples($this->exampleRepo),
            new RunAllExamples($this->runner),
            new RunOneExample($this->runner, $this->exampleRepo),
            new ShowExample($this->exampleRepo),
            new MarkSnippets(
                $this->docRepository,
            ),
            new MarkSnippetsRecursively(
                $this->fileDiscoveryService,
                $this->batchProcessingService,
            ),
            new ExtractCodeBlocks(
                $this->docRepository,
            ),
        ]);
    }
}