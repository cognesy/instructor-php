<?php declare(strict_types=1);
namespace Cognesy\Evals;

use Cognesy\Evals\Console\Display;
use Cognesy\Evals\Contracts\CanGenerateObservations;
use Cognesy\Evals\Contracts\CanObserveExperiment;
use Cognesy\Evals\Contracts\CanRunExecution;
use Cognesy\Evals\Events\ExperimentDone;
use Cognesy\Evals\Events\ExperimentStarted;
use Cognesy\Evals\Observation\MakeObservations;
use Cognesy\Evals\Observers\Aggregate\ExperimentFailureRate;
use Cognesy\Evals\Observers\Aggregate\ExperimentLatency;
use Cognesy\Evals\Observers\Measure\DurationObserver;
use Cognesy\Evals\Observers\Measure\TokenUsageObserver;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Utils\Data\DataMap;
use Cognesy\Utils\Uuid;
use DateTime;
use Exception;
use Generator;
use Psr\EventDispatcher\EventDispatcherInterface;

class Experiment {
    use Traits\Experiment\HandlesAccess;

    private EventDispatcherInterface $events;
    private array $defaultProcessors = [
        DurationObserver::class,
        TokenUsageObserver::class,
        ExperimentLatency::class,
        ExperimentFailureRate::class,
    ];

    private Display $display;
    private Generator $cases;
    private CanRunExecution $executor;
    private array $processors = [];
    private array $postprocessors = [];

    readonly private string $id;
    private ?DateTime $startedAt = null;
    private float $timeElapsed = 0.0;
    private DataMap $data;

    /** @var Execution[] */
    private array $executions = [];
    /** @var Exception[] */
    private array $exceptions = [];

    /** @var Observation[] */
    private array $observations = [];

    public function __construct(
        Generator       $cases,
        CanRunExecution $executor,
        array|object    $processors,
        array|object    $postprocessors,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
        $this->id = Uuid::uuid4();
        $this->display = new Display();
        $this->data = new DataMap();

        $this->cases = $cases;
        $this->executor = $executor;
        $this->processors = match (true) {
            is_array($processors) => $processors,
            default => [$processors],
        };
        $this->postprocessors = match (true) {
            is_array($postprocessors) => $postprocessors,
            default => [$postprocessors],
        };
    }

    // PUBLIC //////////////////////////////////////////////////

    /**
     * @return Observation[]
     */
    public function execute() : array {
        $this->startedAt = new DateTime();
        $this->events->dispatch(new ExperimentStarted($this->toArray()));
        $this->display->header($this);

        // execute cases
        foreach ($this->cases as $case) {
            $execution = $this->executeCase($case);
            $this->display->displayExecution($execution);
        }
        $this->timeElapsed = microtime(true) - $this->startedAt->getTimestamp();

        $this->observations = $this->makeObservations();

        $this->display->footer($this);
        if (!empty($this->exceptions)) {
            $this->display->displayExceptions($this->exceptions);
        }

        $this->events->dispatch(new ExperimentDone($this->toArray()));
        return $this->summaries();
    }

    public function toArray() : array {
        return [
            'id' => $this->id,
            'data' => $this->data->toArray(),
            'executions' => array_map(fn($e) => $e->id(), $this->executions),
        ];
    }

    // INTERNAL /////////////////////////////////////////////////

    private function executeCase(mixed $case) : Execution {
        $execution = $this->makeExecution($case);
        try {
            $execution->execute();
        } catch(Exception $e) {
            $this->exceptions[$execution->id()] = $execution->exception();
        }
        $this->executions[] = $execution;
        return $execution;
    }

    private function makeExecution(mixed $case) : Execution {
        $caseData = match(true) {
            is_array($case) => $case,
            method_exists($case, 'toArray') => $case->toArray(),
            default => (array) $case,
        };
        return (new Execution(
            case: $caseData,
            events: $this->events)
        )
            ->withExecutor($this->executor)
            ->withProcessors($this->processors)
            ->withPostprocessors($this->postprocessors);
    }

    private function makeObservations() : array {
        // execute observers
        $observations = MakeObservations::for($this)
            ->withObservers([$this->processors, $this->defaultProcessors])
            ->only([CanObserveExperiment::class, CanGenerateObservations::class]);

        // execute postprocessors (e.g. summarizers)
        $summaries = MakeObservations::for($this)
            ->withObservers([$this->postprocessors])
            ->only([CanObserveExperiment::class, CanGenerateObservations::class]);

        return array_filter(array_merge($observations, $summaries));
    }
}
