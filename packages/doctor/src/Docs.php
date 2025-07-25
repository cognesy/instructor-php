<?php declare(strict_types=1);

namespace Cognesy\Doctor;

use Cognesy\Config\BasePath;
use Cognesy\Doctor\Docgen\Commands\ClearDocs;
use Cognesy\Doctor\Docgen\Commands\GenerateExamplesCommand;
use Cognesy\Doctor\Docgen\Commands\GeneratePackagesCommand;
use Cognesy\Doctor\Docgen\MintlifyDocGenerator;
use Cognesy\Doctor\Doctest\Commands\ExtractCodeBlocks;
use Cognesy\Doctor\Doctest\Commands\GenerateDocs;
use Cognesy\Doctor\Doctest\Commands\MarkSnippets;
use Cognesy\Doctor\Doctest\Commands\MarkSnippetsRecursively;
use Cognesy\Doctor\Doctest\Services\BatchProcessingService;
use Cognesy\Doctor\Doctest\Services\DocRepository;
use Cognesy\Doctor\Doctest\Services\FileDiscoveryService;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Filesystem\Filesystem;

class Docs extends Application
{
    private MintlifyDocGenerator $docGen;
    private Filesystem $filesystem;
    private DocRepository $docRepository;
    private FileDiscoveryService $fileDiscoveryService;
    private BatchProcessingService $batchProcessingService;

    public function __construct() {
        parent::__construct('Instructor Docs // Documentation Automation', '1.0.0');

        $this->registerServices();
        $this->registerCommands();
    }

    private ExampleRepository $examples;
    private string $docsSourceDir;
    private string $docsTargetDir;
    private string $cookbookTargetDir;
    private string $mintlifySourceIndexFile;
    private string $mintlifyTargetIndexFile;
    private string $codeblocksDir;
    private array $dynamicGroups;

    private function registerServices(): void
    {
        // Example repository for docs generation
        $this->examples = new ExampleRepository(
            BasePath::get('examples'),
        );

        $this->docsSourceDir = BasePath::get('docs');
        $this->docsTargetDir = BasePath::get('docs-build');
        $this->cookbookTargetDir = BasePath::get('docs-build/cookbook');
        $this->mintlifySourceIndexFile = BasePath::get('docs/mint.json');
        $this->mintlifyTargetIndexFile = BasePath::get('docs-build/mint.json');
        $this->codeblocksDir = BasePath::get('codeblocks');
        $this->dynamicGroups = [
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
        ];

        $this->docGen = new MintlifyDocGenerator(
            examples: $this->examples,
            docsSourceDir: $this->docsSourceDir,
            docsTargetDir: $this->docsTargetDir,
            cookbookTargetDir: $this->cookbookTargetDir,
            mintlifySourceIndexFile: $this->mintlifySourceIndexFile,
            mintlifyTargetIndexFile: $this->mintlifyTargetIndexFile,
            dynamicGroups: $this->dynamicGroups,
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
        // Register docs-specific commands
        $this->addCommands([
            new GenerateDocs(
                $this->docGen,
                $this->examples,
                $this->docsSourceDir,
                $this->docsTargetDir,
                $this->cookbookTargetDir,
                $this->mintlifySourceIndexFile,
                $this->mintlifyTargetIndexFile,
                $this->codeblocksDir,
                $this->dynamicGroups
            ),
            new GenerateExamplesCommand(
                $this->examples,
                $this->docsSourceDir,
                $this->docsTargetDir,
                $this->cookbookTargetDir,
                $this->mintlifySourceIndexFile,
                $this->mintlifyTargetIndexFile,
                $this->codeblocksDir,
                $this->dynamicGroups
            ),
            new GeneratePackagesCommand(
                $this->examples,
                $this->docsSourceDir,
                $this->docsTargetDir,
                $this->cookbookTargetDir,
                $this->mintlifySourceIndexFile,
                $this->mintlifyTargetIndexFile,
                $this->codeblocksDir,
                $this->dynamicGroups
            ),
            new ClearDocs($this->docGen),
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