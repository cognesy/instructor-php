<?php

namespace Cognesy\Addons\Evals\Traits\Experiment;

use Cognesy\Addons\Evals\Execution;
use Cognesy\Addons\Evals\Observation;
use Cognesy\Addons\Evals\Observation\SelectObservations;
use Cognesy\LLM\LLM\Data\Usage;
use Cognesy\Utils\DataMap;
use DateTime;

trait HandlesAccess
{
    public function id() : string {
        return $this->id;
    }

    public function startedAt() : DateTime {
        return $this->startedAt;
    }

    public function timeElapsed() : float {
        return $this->timeElapsed;
    }

    public function usage() : Usage {
        return $this->usage;
    }

    public function data() : DataMap {
        return $this->data;
    }

    /**
     * @return Execution[]
     */
    public function executions() : array {
        return $this->executions;
    }

    /**
     * @return \Cognesy\Addons\Evals\Observation[]
     */
    public function metrics(string $name) : array {
        return SelectObservations::from($this->observations)->withTypes(['metric'])->get($name);
    }

    /**
     * @return \Cognesy\Addons\Evals\Observation[]
     */
    public function summaries() : array {
        return SelectObservations::from($this->observations)->withTypes(['summary'])->all();
    }

    /**
     * @return \Cognesy\Addons\Evals\Observation[]
     */
    public function feedback() : array {
        return SelectObservations::from($this->observations)->withTypes(['feedback'])->all();
    }

    public function hasObservations() : bool {
        return count($this->observations) > 0;
    }

    /**
     * @return \Cognesy\Addons\Evals\Observation[]
     */
    public function observations() : array {
        return $this->observations;
    }

    /**
     * @return \Cognesy\Addons\Evals\Observation[]
     */
    public function executionObservations() : array {
        $observations = [];
        foreach($this->executions as $execution) {
            foreach($execution->observations() as $observation) {
                $observations[] = $observation;
            }
        }
        return $observations;
    }

}