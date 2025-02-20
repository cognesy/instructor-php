<?php
namespace Cognesy\Addons\Evals;

use Cognesy\Addons\Evals\Console\Display;
use Cognesy\Addons\Evals\Contracts\CanRunExecution;
use Cognesy\Addons\Evals\Observers\Aggregate\ExperimentFailureRate;
use Cognesy\Addons\Evals\Observers\Aggregate\ExperimentLatency;
use Cognesy\Addons\Evals\Observers\Measure\DurationObserver;
use Cognesy\Addons\Evals\Observers\Measure\TokenUsageObserver;
use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\LLM\LLM\Data\Usage;
use Cognesy\Utils\DataMap;
use Cognesy\Utils\Uuid;
use DateTime;
use Exception;
use Generator;

class Experiment {
    use \Cognesy\Addons\Evals\Traits\Experiment\HandlesAccess;
    use \Cognesy\Addons\Evals\Traits\Experiment\HandlesExecution;

    private EventDispatcher $events;
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
    private ?Usage $usage = null;
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
        EventDispatcher $events = null,
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

    public function toArray() : array {
        return [
            'id' => $this->id,
            'data' => $this->data->toArray(),
            'executions' => array_map(fn($e) => $e->id(), $this->executions),
        ];
    }
}
