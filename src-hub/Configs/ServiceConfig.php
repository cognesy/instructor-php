<?php

namespace Cognesy\InstructorHub\Configs;

use Cognesy\Instructor\Container\Container;
use Cognesy\Instructor\Container\Contracts\CanAddConfiguration;

use Cognesy\InstructorHub\Commands\ClearDocs;
use Cognesy\InstructorHub\Commands\GenerateDocs;
use Cognesy\InstructorHub\Commands\ListAllExamples;
use Cognesy\InstructorHub\Commands\RunAllExamples;
use Cognesy\InstructorHub\Commands\RunOneExample;
use Cognesy\InstructorHub\Commands\ShowExample;
use Cognesy\InstructorHub\Core\CommandProvider;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\MintlifyDocGenerator;
use Cognesy\InstructorHub\Services\Runner;

class ServiceConfig implements CanAddConfiguration
{
    public function addConfiguration(Container $config) : void {

        /// SERVICES //////////////////////////////////////////////////////////////////

        $config->object(
            class: CommandProvider::class,
            context: [
                'config' => $config,
                'commands' => [
                    GenerateDocs::class,
                    ClearDocs::class,
                    ListAllExamples::class,
                    RunAllExamples::class,
                    RunOneExample::class,
                    ShowExample::class,
                ],
            ],
        );

        $config->object(
            class: ExampleRepository::class,
            context: [
                'baseDir' => __DIR__ . '/../../examples/',
            ],
        );

        $config->object(
            class: Runner::class,
            context: [
                'examples' => $config->reference(ExampleRepository::class),
                'displayErrors' => true,
                'stopAfter' => 0,
                'stopOnError' => true,
            ],
        );

        $config->object(
            class: MintlifyDocGenerator::class,
            context: [
                'examples' => $config->reference(ExampleRepository::class),
                'mintlifyCookbookDir' => __DIR__ . '/../../docs/cookbook',
                'mintlifyIndexFile' => __DIR__ . '/../../docs/mint.json',
                'dynamicGroups' => [
                    'Basics',
                    'Advanced',
                    'Prompting',
                    'Techniques',
                    'Troubleshooting',
                    'LLM API Support',
                    'Extras'
                ],
            ],
        );
    }
}
