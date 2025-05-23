<?php

namespace Cognesy\InstructorHub;

use Cognesy\InstructorHub\Commands\ClearDocs;
use Cognesy\InstructorHub\Commands\GenerateDocs;
use Cognesy\InstructorHub\Commands\ListAllExamples;
use Cognesy\InstructorHub\Commands\RunAllExamples;
use Cognesy\InstructorHub\Commands\RunOneExample;
use Cognesy\InstructorHub\Commands\ShowExample;
use Cognesy\InstructorHub\Core\CliApp;
use Cognesy\InstructorHub\Core\CommandProvider;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\MintlifyDocGenerator;
use Cognesy\InstructorHub\Services\Runner;
use Cognesy\Utils\BasePath;

class Hub extends CliApp
{
    public string $name = "Hub // Instructor for PHP";
    public string $description = " (^) Get typed structured outputs from LLMs";

    public function __construct()
    {
        $exampleRepo = new ExampleRepository(
            BasePath::get('examples'),
        );

        $docGen = new MintlifyDocGenerator(
            examples: $exampleRepo,
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
            ]
        );

        $runner = new Runner(
            examples: $exampleRepo,
            displayErrors: true,
            stopAfter: 0,
            stopOnError: false
        );

        $commands = [
            new GenerateDocs($docGen),
            new ClearDocs($docGen),
            new ListAllExamples($exampleRepo),
            new RunAllExamples($runner),
            new RunOneExample($runner, $exampleRepo),
            new ShowExample($exampleRepo),
        ];

        parent::__construct(new CommandProvider($commands));
    }
}
