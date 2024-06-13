<?php

namespace Cognesy\InstructorHub\Configs;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;

use Cognesy\InstructorHub\Commands\GenerateDocs;
use Cognesy\InstructorHub\Commands\ListAllExamples;
use Cognesy\InstructorHub\Commands\RunAllExamples;
use Cognesy\InstructorHub\Commands\RunOneExample;
use Cognesy\InstructorHub\Commands\ShowExample;
use Cognesy\InstructorHub\Core\CommandProvider;
use Cognesy\InstructorHub\Services\DocGenerator;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\Runner;

class ServiceConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config) : void {

        /// SERVICES //////////////////////////////////////////////////////////////////

        $config->declare(
            class: CommandProvider::class,
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
                'baseDir' => __DIR__ . '/../../examples/',
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
                'hubDocsDir' => __DIR__ . '/../../docs/hub',
                'mkDocsFile' => __DIR__ . '/../../mkdocs.yml',
                'sectionStartMarker' => '###HUB-INDEX-START###',
                'sectionEndMarker' => '###HUB-INDEX-END###',
            ],
        );
    }
}