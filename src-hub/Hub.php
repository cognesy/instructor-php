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

class Hub extends CliApp
{
    public string $name = "Hub // Instructor for PHP";
    public string $description = " (^) Get typed structured outputs from LLMs";

    public function __construct()
    {
        $exampleRepo = new ExampleRepository(
            __DIR__ . '/../examples/'
        );

        $docGen = new MintlifyDocGenerator(
                $exampleRepo,
                __DIR__ . '/../docs/cookbook',
                __DIR__ . '/../docs/mint.json',
                [
                    'Basics',
                    'Advanced',
                    'Prompting',
                    'Troubleshooting',
                    'LLM API Support',
                    'Extras',
                    'Zero-Shot Prompting',
                    'Few-Shot Prompting',
                    'Thought Generation',
                    'Ensembling',
                    'Self-Criticism',
                    'Decomposition',
                    'Miscellaneous',
                ],
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
