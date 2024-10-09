<?php
namespace Cognesy\Evals\Evals\Contracts;

use Cognesy\Evals\Evals\Data\EvalInput;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

interface CanExecuteExperiment
{
    public static function executeFor(EvalInput $input): self;
    public function getLLMResponse(): LLMResponse;
    public function getAnswer(): mixed;
}