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
                'Instructor - Basics',
                'Instructor - Advanced',
                'Instructor - Prompting',
                'Instructor - Troubleshooting',
                'Instructor - API Support',

                "Polyglot - LLM Basics",
                "Polyglot - LLM Advanced",
                "Polyglot - LLM Troubleshooting",
                "Polyglot - LLM API Support",
                "Polyglot - LLM Extras",

                'Prompting - Extras',
                'Prompting - Zero-Shot Prompting',
                'Prompting - Few-Shot Prompting',
                'Prompting - Thought Generation',
                'Prompting - Ensembling',
                'Prompting - Self-Criticism',
                'Prompting - Decomposition',
                'Prompting - Miscellaneous',
            ]
        );

        $runner = new Runner($exampleRepo, true, 0, true);

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
