<?php

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\InstructorHub\Commands\GenerateDocs;
use Cognesy\InstructorHub\Commands\ListAllExamples;
use Cognesy\InstructorHub\Commands\RunAllExamples;
use Cognesy\InstructorHub\Commands\RunOneExample;
use Cognesy\InstructorHub\Commands\ShowExample;
use Cognesy\InstructorHub\Core\CommandRegistry;
use Cognesy\InstructorHub\Services\DocGenerator;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\Runner;


function hub(Configuration $config) : Configuration
{
    /// SERVICES //////////////////////////////////////////////////////////////////

    $config->declare(
        class: CommandRegistry::class,
        context: [
            'config' => $config,
            'commands' => [
                GenerateDocs::class,
                ListAllExamples::class,
                RunAllExamples::class,
                RunOneExample::class,
                ShowExample::class,
            ],
        ],
    );

    $config->declare(
        class: ExampleRepository::class,
        context: [
            'baseDir' => __DIR__ . '/../examples/',
        ],
    );

    $config->declare(
        class: Runner::class,
        context: [
            'examples' => $config->reference(ExampleRepository::class),
            'displayErrors' => true,
            'stopAfter' => 0,
            'stopOnError' => true,
        ],
    );

    $config->declare(
        class: DocGenerator::class,
        context: [
            'examples' => $config->reference(ExampleRepository::class),
            'hubDocsDir' => __DIR__ . '/../docs/hub',
            'mkDocsFile' => __DIR__ . '/../mkdocs.yml',
            'sectionStartMarker' => '###HUB-INDEX-START###',
            'sectionEndMarker' => '###HUB-INDEX-END###',
        ],
    );

    /// COMMANDS //////////////////////////////////////////////////////////////////

    $config->declare(
        class: GenerateDocs::class,
        context: [
            'docGen' => $config->reference(DocGenerator::class),
        ],
    );

    $config->declare(
        class: ListAllExamples::class,
        context: [
            'examples' => $config->reference(ExampleRepository::class),
        ],
    );

    $config->declare(
        class: RunAllExamples::class,
        context: [
            'runner' => $config->reference(Runner::class),
        ],
    );

    $config->declare(
        class: RunOneExample::class,
        context: [
            'runner' => $config->reference(Runner::class),
            'examples' => $config->reference(ExampleRepository::class),
        ],
    );

    $config->declare(
        class: ShowExample::class,
        context: [
            'examples' => $config->reference(ExampleRepository::class),
        ],
    );

    return $config;
}