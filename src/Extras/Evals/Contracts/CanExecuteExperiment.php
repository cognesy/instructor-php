<?php
namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Experiment;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

interface CanExecuteExperiment
{
    public function execute(Experiment $experiment): LLMResponse;
}