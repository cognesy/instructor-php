<?php

namespace Cognesy\InstructorHub\Configs;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Configuration\Contracts\CanAddConfiguration;

use Cognesy\InstructorHub\Commands\GenerateDocs;
use Cognesy\InstructorHub\Commands\ListAllExamples;
use Cognesy\InstructorHub\Commands\RunAllExamples;
use Cognesy\InstructorHub\Commands\RunOneExample;
use Cognesy\InstructorHub\Commands\ShowExample;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\MintlifyDocGenerator;
use Cognesy\InstructorHub\Services\Runner;

class CommandConfig implements CanAddConfiguration
{
    public function addConfiguration(Configuration $config): void {

        /// COMMANDS //////////////////////////////////////////////////////////////////

        $config->declare(
            class: GenerateDocs::class,
            context: [
                'docGen' => $config->reference(MintlifyDocGenerator::class),
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

    }
}