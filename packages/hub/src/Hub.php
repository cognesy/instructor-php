<?php declare(strict_types=1);

namespace Cognesy\InstructorHub;

use Cognesy\Config\BasePath;
use Cognesy\InstructorHub\Commands\ListAllExamples;
use Cognesy\InstructorHub\Commands\RunAllExamples;
use Cognesy\InstructorHub\Commands\RunOneExample;
use Cognesy\InstructorHub\Commands\ShowExample;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\Runner;
use Symfony\Component\Console\Application;

class Hub extends Application
{
    private ExampleRepository $exampleRepo;
    private Runner $runner;

    public function __construct() {
        parent::__construct('Hub // Instructor for PHP', '1.0.0');
        //$this->setDescription('(^) Get typed structured outputs from LLMs');

        $this->registerServices();
        $this->registerCommands();
    }

    private function registerServices(): void
    {
        $this->exampleRepo = new ExampleRepository(
            BasePath::get('examples'),
        );
        $this->runner = new Runner(
            examples: $this->exampleRepo,
            displayErrors: true,
            stopAfter: 0,
            stopOnError: false,
        );

    }

    private function registerCommands(): void
    {
        // Register example-related commands
        $this->addCommands([
            new ListAllExamples($this->exampleRepo),
            new RunAllExamples($this->runner),
            new RunOneExample($this->runner, $this->exampleRepo),
            new ShowExample($this->exampleRepo),
        ]);
    }
}