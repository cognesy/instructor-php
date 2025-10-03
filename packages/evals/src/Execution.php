<?php declare(strict_types=1);

namespace Cognesy\Evals;

use Cognesy\Evals\Contracts\CanGenerateObservations;
use Cognesy\Evals\Contracts\CanObserveExecution;
use Cognesy\Evals\Contracts\CanRunExecution;
use Cognesy\Evals\Events\ExecutionDone;
use Cognesy\Evals\Events\ExecutionFailed;
use Cognesy\Evals\Events\ExecutionProcessed;
use Cognesy\Evals\Observation\MakeObservations;
use Cognesy\Evals\Observers\Measure\DurationObserver;
use Cognesy\Evals\Observers\Measure\TokenUsageObserver;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Data\DataMap;
use Cognesy\Utils\Uuid;
use DateTime;
use Exception;
use Psr\EventDispatcher\EventDispatcherInterface;

class Execution
{
    use Traits\Execution\HandlesAccess;

    private EventDispatcherInterface $events;

    /** @var list<class-string<CanGenerateObservations>> */
    private array $defaultObservers = [
        DurationObserver::class,
        TokenUsageObserver::class,
    ];

    /** @phpstan-ignore-next-line */
    private CanRunExecution $action;
    private array $processors = [];
    private array $postprocessors = [];

    private string $id;
    private ?DateTime $startedAt = null;
    private float $timeElapsed = 0.0;
    private Usage $usage;
    /** @var DataMap<string, mixed> */
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

    public function execute() : void {
        if (!isset($this->action)) {
            throw new \RuntimeException('Executor must be set via withExecutor() before calling execute()');
        }
        $this->startedAt = new DateTime();
        $time = microtime(true);
        try {
            $this->action->run($this);
            $this->events->dispatch(new ExecutionDone($this->toArray()));
        } catch(Exception $e) {
            $this->timeElapsed = microtime(true) - $time;
            $this->data()->set('output.notes', $e->getMessage());
            $this->exception = $e;
            $this->events->dispatch(new ExecutionFailed($this->toArray()));
            throw $e;
        }
        $this->timeElapsed = microtime(true) - $time;
        $this->data()->set('output.notes', $this->get('response')?->content());
        $this->usage = $this->get('response')?->usage() ?? Usage::none();
        $this->observations = $this->makeObservations();
        $this->events->dispatch(new ExecutionProcessed($this->toArray()));
    }

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

    // INTERNAL //////////////////////////////////////////////////

    private function makeObservations() : array {
        $observations = MakeObservations::for($this)
            ->withObservers([
                $this->processors,
                $this->defaultObservers,
            ])
            ->only([
                CanObserveExecution::class,
                CanGenerateObservations::class,
            ]);

        $summaries = MakeObservations::for($this)
            ->withObservers([
                $this->postprocessors
            ])
            ->only([
                CanObserveExecution::class,
                CanGenerateObservations::class,
            ]);

        return array_filter(array_merge($observations, $summaries));
    }
}
