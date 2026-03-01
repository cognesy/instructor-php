<?php declare(strict_types=1);

namespace Cognesy\Doctools;

use Cognesy\Config\BasePath;
use Cognesy\InstructorHub\Config\ExampleGroupingConfig;
use Cognesy\InstructorHub\Config\ExampleSourcesConfig;
use Cognesy\Doctools\Docgen\MintlifyDocumentation;
use Cognesy\Doctools\Docgen\MkDocsDocumentation;
use Cognesy\Doctools\Docgen\Data\DocumentationConfig;
use Cognesy\Doctools\Docgen\Commands\ClearMintlifyDocsCommand;
use Cognesy\Doctools\Docgen\Commands\ClearMkDocsCommand;
use Cognesy\Doctools\Docgen\Commands\GenerateExamplesCommand;
use Cognesy\Doctools\Docgen\Commands\GenerateLlmsCommand;
use Cognesy\Doctools\Docgen\Commands\GenerateMintlifyCommand;
use Cognesy\Doctools\Docgen\Commands\GenerateMkDocsCommand;
use Cognesy\Doctools\Docgen\Commands\GeneratePackagesCommand;
use Cognesy\Doctools\Doctest\Commands\ExtractCodeBlocks;
use Cognesy\Doctools\Doctest\Commands\MarkSnippets;
use Cognesy\Doctools\Doctest\Commands\MarkSnippetsRecursively;
use Cognesy\Doctools\Doctest\Commands\ValidateCodeBlocks;
use Cognesy\Doctools\Quality\Commands\RunDocsQualityCommand;
use Cognesy\Doctools\Lesson\Commands\MakeLesson;
use Cognesy\Doctools\Lesson\Commands\MakeLessonImage;
use Cognesy\Doctools\Doctest\Services\DocRepository;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Filesystem\Filesystem;
use Cognesy\Events\Dispatchers\EventDispatcher;

class Docs extends Application
{
    private MintlifyDocumentation $docGen;
    private MkDocsDocumentation $mkDocsGen;
    private Filesystem $filesystem;
    private DocRepository $docRepository;
    private EventDispatcher $eventDispatcher;
    private ExampleRepository $examples;
    private string $docsSourceDir;
    private string $docsTargetDir;
    private string $mkdocsTargetDir;
    private string $cookbookTargetDir;
    private string $mkdocsCookbookTargetDir;
    private string $mintlifySourceIndexFile;
    private string $mintlifyTargetIndexFile;
    private string $codeblocksDir;
    /** @var array<int, string> */
    private array $dynamicGroups;

    public function __construct() {
        // Initialize all properties in registerServices() before use in registerCommands()
        $this->registerServices();

        parent::__construct('Instructor Docs // Documentation Automation', '1.0.0');

        $this->registerCommands();
    }

    private function registerServices(): void
    {
        // Example repository for docs generation
        $sources = (new ExampleSourcesConfig())->load();
        $grouping = (new ExampleGroupingConfig())->load();
        $this->examples = new ExampleRepository($sources, $grouping);

        $this->docsSourceDir = BasePath::get('docs');
        $this->docsTargetDir = BasePath::get('docs-build');
        $this->mkdocsTargetDir = BasePath::get('docs-mkdocs');
        $this->cookbookTargetDir = BasePath::get('docs-build/cookbook');
        $this->mkdocsCookbookTargetDir = BasePath::get('docs-mkdocs/cookbook');
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

        $config = new DocumentationConfig(
            docsSourceDir: $this->docsSourceDir,
            docsTargetDir: $this->docsTargetDir,
            cookbookTargetDir: $this->cookbookTargetDir,
            mintlifySourceIndexFile: $this->mintlifySourceIndexFile,
            mintlifyTargetIndexFile: $this->mintlifyTargetIndexFile,
            codeblocksDir: $this->codeblocksDir,
            dynamicGroups: $this->dynamicGroups,
        );
        
        $this->docGen = new MintlifyDocumentation(
            examples: $this->examples,
            config: $config,
        );

        $mkDocsConfig = DocumentationConfig::createForMkDocs(
            docsSourceDir: $this->docsSourceDir,
            mkdocsTargetDir: $this->mkdocsTargetDir,
            mkdocsCookbookTargetDir: $this->mkdocsCookbookTargetDir,
            codeblocksDir: $this->codeblocksDir,
            dynamicGroups: $this->dynamicGroups,
        );

        $this->mkDocsGen = new MkDocsDocumentation(
            examples: $this->examples,
            config: $mkDocsConfig,
        );

        $this->filesystem = new Filesystem();
        $this->docRepository = new DocRepository($this->filesystem);
        $this->eventDispatcher = new EventDispatcher();
    }

    private function registerCommands(): void
    {
        // All properties are initialized in registerServices() which is called before this method
        assert(isset($this->examples, $this->docsSourceDir, $this->docsTargetDir, $this->cookbookTargetDir,
            $this->mintlifySourceIndexFile, $this->mintlifyTargetIndexFile, $this->codeblocksDir, $this->dynamicGroups, $this->docGen));

        // Register docs-specific commands
        $this->addCommands([
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
            new ClearMintlifyDocsCommand($this->docGen),
            new GenerateMintlifyCommand(
                $this->examples,
                $this->docsSourceDir,
                $this->docsTargetDir,
                $this->cookbookTargetDir,
                $this->mintlifySourceIndexFile,
                $this->mintlifyTargetIndexFile,
                $this->codeblocksDir,
                $this->dynamicGroups
            ),
            new GenerateMkDocsCommand(
                $this->examples,
                $this->docsSourceDir,
                $this->mkdocsTargetDir,
                $this->mkdocsCookbookTargetDir,
                $this->codeblocksDir,
                $this->dynamicGroups
            ),
            new ClearMkDocsCommand($this->mkDocsGen),
            new GenerateLlmsCommand(
                $this->examples,
                $this->mkdocsTargetDir,
            ),
            new MarkSnippets(
                $this->docRepository,
            ),
            new MarkSnippetsRecursively(
                $this->docRepository,
            ),
            new ExtractCodeBlocks(
                $this->docRepository,
                $this->eventDispatcher,
            ),
            new ValidateCodeBlocks($this->eventDispatcher),
            new RunDocsQualityCommand(),
            new MakeLesson($this->examples),
            new MakeLessonImage(),
        ]);
    }
}
