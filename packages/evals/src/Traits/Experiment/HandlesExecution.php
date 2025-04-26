<?php

namespace Cognesy\Evals\Traits\Experiment;

use Cognesy\Evals\Contracts\CanGenerateObservations;
use Cognesy\Evals\Contracts\CanObserveExperiment;
use Cognesy\Evals\Events\ExperimentDone;
use Cognesy\Evals\Events\ExperimentStarted;
use Cognesy\Evals\Execution;
use Cognesy\Evals\Observation;
use Cognesy\Evals\Observation\MakeObservations;
use Cognesy\Polyglot\LLM\Data\Usage;
use DateTime;
use Exception;

trait HandlesExecution
{
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
        $this->usage = $this->accumulateUsage();
        $this->timeElapsed = microtime(true) - $this->startedAt->getTimestamp();

        $this->observations = $this->makeObservations();

        $this->display->footer($this);
        if (!empty($this->exceptions)) {
            $this->display->displayExceptions($this->exceptions);
        }

        $this->events->dispatch(new ExperimentDone($this->toArray()));
        return $this->summaries();
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
        return (new Execution(case: $caseData))
            ->withExecutor($this->executor)
            ->withProcessors($this->processors)
            ->withPostprocessors($this->postprocessors);
    }

    private function accumulateUsage() : Usage {
        $usage = new Usage();
        foreach ($this->executions as $execution) {
            $usage->accumulate($execution->usage());
        }
        return $usage;
    }

    private function makeObservations() : array {
        // execute observers
        $observations = MakeObservations::for($this)
            ->withObservers([$this->processors, $this->defaultProcessors])
            ->only([CanObserveExperiment::class, CanGenerateObservations::class]);

        // execute summarizers
        $summaries = MakeObservations::for($this)
            ->withObservers([$this->postprocessors])
            ->only([CanObserveExperiment::class, CanGenerateObservations::class]);

        return array_filter(array_merge($observations, $summaries));
    }
}