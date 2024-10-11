<?php
namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Data\Experiment;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

interface CanExecuteExperiment
{
    public function execute(Experiment $experiment): void;
    public function getLLMResponse(): LLMResponse;
    public function getAnswer(): mixed;
}