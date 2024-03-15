<?php

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\InstructorHub\Commands\GenerateDocs;
use Cognesy\InstructorHub\Commands\ListAllExamples;
use Cognesy\InstructorHub\Commands\RunAllExamples;
use Cognesy\InstructorHub\Commands\RunOneExample;
use Cognesy\InstructorHub\Commands\ShowExample;
use Cognesy\InstructorHub\Commands\ShowHelp;
use Cognesy\InstructorHub\Core\CommandRegistry;
use Cognesy\InstructorHub\Services\DocGenerator;
use Cognesy\InstructorHub\Services\Examples;
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
        class: Examples::class,
        context: [
            'baseDir' => __DIR__ . '/../examples/',
        ],
    );

    $config->declare(
        class: Runner::class,
        context: [
            'examples' => $config->reference(Examples::class),
            'displayErrors' => false,
            'stopAfter' => 0,
            'stopOnError' => true,
        ],
    );

    $config->declare(
        class: DocGenerator::class,
    );

    /// COMMANDS //////////////////////////////////////////////////////////////////

    $config->declare(
        class: GenerateDocs::class,
    );

    $config->declare(
        class: ListAllExamples::class,
        context: [
            'examples' => $config->reference(Examples::class),
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
        ],
    );

    $config->declare(
        class: ShowExample::class,
        context: [
            'examples' => $config->reference(Examples::class),
        ],
    );

    return $config;
}