<?php

namespace Cognesy\Addons\Evals\Traits\Execution;

use Cognesy\Addons\Evals\Contracts\CanGenerateObservations;
use Cognesy\Addons\Evals\Contracts\CanObserveExecution;
use Cognesy\Addons\Evals\Events\ExecutionDone;
use Cognesy\Addons\Evals\Events\ExecutionFailed;
use Cognesy\Addons\Evals\Events\ExecutionProcessed;
use Cognesy\Addons\Evals\Observation\MakeObservations;
use Cognesy\Polyglot\LLM\Data\Usage;
use DateTime;
use Exception;

trait HandlesExecution
{
    public function execute() : void {
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