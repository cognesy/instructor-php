<?php

namespace Cognesy\Instructor\Extras\Evals;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Extras\Evals\Contracts\CanObserveExecution;
use Cognesy\Instructor\Extras\Evals\Contracts\CanRunExecution;
use Cognesy\Instructor\Extras\Evals\Observers\Measure\DurationObserver;
use Cognesy\Instructor\Extras\Evals\Observers\Measure\TokenUsageObserver;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\DataMap;
use Cognesy\Instructor\Utils\Uuid;
use DateTime;
use Exception;

/**
 *
 */
class Execution
{
    use Traits\Execution\HandlesAccess;
    use Traits\Execution\HandlesExecution;

    private EventDispatcher $events;

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
        EventDispatcher $events = null,
    ) {
        $this->events = $events ?? new EventDispatcher();
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
