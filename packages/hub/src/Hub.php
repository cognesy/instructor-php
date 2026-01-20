<?php declare(strict_types=1);

namespace Cognesy\InstructorHub;

use Cognesy\InstructorHub\Config\ExampleSourcesConfig;
use Cognesy\InstructorHub\Config\ExampleGroupingConfig;
use Cognesy\InstructorHub\Commands\CleanCommand;
use Cognesy\InstructorHub\Commands\EnhancedRunAllExamples;
use Cognesy\InstructorHub\Commands\ErrorsCommand;
use Cognesy\InstructorHub\Commands\HubHelpCommand;
use Cognesy\InstructorHub\Commands\ListAllExamples;
use Cognesy\InstructorHub\Commands\RawCommand;
use Cognesy\InstructorHub\Commands\RunOneExample;
use Cognesy\InstructorHub\Commands\ShowExample;
use Cognesy\InstructorHub\Commands\StaleCommand;
use Cognesy\InstructorHub\Commands\StatsCommand;
use Cognesy\InstructorHub\Commands\StatusCommand;
use Cognesy\InstructorHub\Contracts\CanExecuteExample;
use Cognesy\InstructorHub\Services\EnhancedRunner;
use Cognesy\InstructorHub\Services\ExampleRepository;
use Cognesy\InstructorHub\Services\ExecutionTracker;
use Cognesy\InstructorHub\Services\StatusRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Hub extends Application
{
    private ExampleRepository $exampleRepo;
    private StatusRepository $statusRepo;
    private ExecutionTracker $tracker;
    private CanExecuteExample $runner;

    public function __construct()
    {
        parent::__construct('Hub - Example Execution & Tracking', '2.0.0');

        $this->registerServices();
        $this->registerCommands();

        // Set help as the default command
        $this->setDefaultCommand('help');
    }

    #[\Override]
    public function getLongVersion(): string
    {
        return <<<'HELP'
<info>Hub - Example Execution & Tracking</info> version <comment>2.0.0</comment>

Hub provides example execution with comprehensive status tracking, selective re-execution, and performance analytics.

<comment>QUICK START:</comment>
  composer hub run 1              Run single example (raw output, colors preserved)
  composer hub list               List all examples
  composer hub all                Run all examples with tracking
  composer hub status             Check execution status
  composer hub stats              View performance analytics

<comment>CORE COMMANDS:</comment>
  list                            List all available examples
  run <example> [--track]         Run single example (raw output by default)
  raw <example>                   Run example with raw, unbuffered output
  all [start] [--filter=X]        Run all/bulk examples with tracking
  errors                          Re-run failed examples
  stale                           Run examples with modified files
  status [--detailed] [--format]  Show execution status and summaries
  stats [--slowest=N]             Show performance metrics and analytics
  clean [--completed] [--all]     Clean status data

<comment>EXAMPLES:</comment>
  composer hub run 35             # Real-time streaming output with colors
  composer hub all --filter=errors # Re-run only failed examples
  composer hub status --detailed   # Per-example breakdown
  composer hub stats --slowest=10  # Show 10 slowest examples

For detailed help on any command: <info>composer hub help <command></info>
For full documentation: see <info>packages/hub/README.md</info>
HELP;
    }

    private function registerServices(): void
    {
        $sources = (new ExampleSourcesConfig())->load();
        $grouping = (new ExampleGroupingConfig())->load();
        $this->exampleRepo = new ExampleRepository($sources, $grouping);

        $this->statusRepo = new StatusRepository();

        $this->tracker = new ExecutionTracker(
            repository: $this->statusRepo,
            examples: $this->exampleRepo,
            autoSave: true,
        );

        $this->runner = new EnhancedRunner(
            timeoutSeconds: 300,
        );

        $this->runner->setTracker($this->tracker);
    }

    private function registerCommands(): void
    {
        $this->addCommands([
            // Custom help command
            new HubHelpCommand(),

            // Existing commands (enhanced or preserved)
            new ListAllExamples($this->exampleRepo),
            new EnhancedRunAllExamples($this->runner, $this->exampleRepo, $this->tracker),
            new RunOneExample($this->runner, $this->exampleRepo),
            new RawCommand($this->exampleRepo),
            new ShowExample($this->exampleRepo, $this->tracker),

            // New tracking commands
            new StatusCommand($this->tracker),
            new StatsCommand($this->tracker),

            // New selective execution commands
            new ErrorsCommand($this->runner, $this->exampleRepo, $this->tracker),
            new StaleCommand($this->runner, $this->exampleRepo, $this->tracker),

            // Maintenance commands
            new CleanCommand($this->tracker, $this->statusRepo),
        ]);
    }

}
