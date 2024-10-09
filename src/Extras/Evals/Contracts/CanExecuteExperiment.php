<?php
namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Data\EvalInput;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

interface CanExecuteExperiment
{
    public function executeFor(EvalInput $input): self;
    public function getLLMResponse(): LLMResponse;
    public function getAnswer(): mixed;
}