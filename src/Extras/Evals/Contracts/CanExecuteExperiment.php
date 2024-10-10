<?php
namespace Cognesy\Instructor\Extras\Evals\Contracts;

use Cognesy\Instructor\Extras\Evals\Data\EvalInput;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

interface CanExecuteExperiment
{
    public function withEvalInput(EvalInput $input): self;
    public function execute(): void;
    public function getLLMResponse(): LLMResponse;
    public function getAnswer(): mixed;
}