<?php

namespace Cognesy\Evals;

use Cognesy\Evals\Contracts\CanObserveExecution;
use Cognesy\Evals\Contracts\CanRunExecution;
use Cognesy\Evals\Observers\Measure\DurationObserver;
use Cognesy\Evals\Observers\Measure\TokenUsageObserver;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\DataMap;
use Cognesy\Utils\Uuid;
use DateTime;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 *
 */
class Execution
{
    use Traits\Execution\HandlesAccess;
    use Traits\Execution\HandlesExecution;

    private EventDispatcherInterface $events;

    /** @var CanObserveExecution[] */
    private array $defaultObservers = [
        DurationObserver::class,
        TokenUsageObserver::class,
    ];

    private CanRunExecution $action;
    private array $processors = [];
    private array $postprocessors = [];

    private string $id;
    private ?DateTime $startedAt = null;
    private float $timeElapsed = 0.0;
    private Usage $usage;
    private DataMap $data;

    private ?Exception $exception = null;

    /** @var Observation[] */
    private array $observations = [];

    public function __construct(
        array $case,
        EventDispatcherInterface $events,
    ) {
        $this->events = $events;
        $this->id = Uuid::uuid4();
        $this->data = new DataMap();
        $this->data->set('case', $case);
        $this->usage = Usage::none();
    }

    // PUBLIC /////////////////////////////////////////////////////////

    public function toArray() : array {
        return [
            'id' => $this->id(),
            'startedAt' => $this->startedAt(),
            'status' => $this->status(),
            'data' => $this->data(),
            'timeElapsed' => $this->timeElapsed(),
            'usage' => $this->usage(),
            'exception' => $this->exception(),
        ];
    }
}
