<?php declare(strict_types=1);

namespace Cognesy\Evals\Traits\Experiment;

use Cognesy\Evals\Execution;
use Cognesy\Evals\Observation;
use Cognesy\Evals\Observation\SelectObservations;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Data\DataMap;
use DateTime;

trait HandlesAccess
{
    public function id() : string {
        return $this->id;
    }

    public function startedAt() : ?DateTime {
        return $this->startedAt;
    }

    public function timeElapsed() : float {
        return $this->timeElapsed;
    }

    public function usage() : Usage {
        $usage = new Usage();
        foreach ($this->executions as $execution) {
            $usage = $usage->withAccumulated($execution->usage());
        }
        return $usage;
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
     * @return Observation[]
     */
    public function metrics(string $name) : array {
        return SelectObservations::from($this->observations)->withTypes(['metric'])->get($name);
    }

    /**
     * @return Observation[]
     */
    public function summaries() : array {
        return SelectObservations::from($this->observations)->withTypes(['summary'])->all();
    }

    /**
     * @return Observation[]
     */
    public function feedback() : array {
        return SelectObservations::from($this->observations)->withTypes(['feedback'])->all();
    }

    public function hasObservations() : bool {
        return count($this->observations) > 0;
    }

    /**
     * @return Observation[]
     */
    public function observations() : array {
        return $this->observations;
    }

    /**
     * @return Observation[]
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