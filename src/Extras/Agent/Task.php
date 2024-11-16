<?php

namespace Cognesy\Instructor\Extras\Agent;

use Cognesy\Instructor\Features\Validation\ValidationResult;

class Task
{
    protected mixed $input;
    protected mixed $output;
    protected bool $isComplete = false;
    protected array $validationResults = [];

    public function __construct(
        mixed $input,
    ) {
        $this->input = $input;
    }

    public function input(): mixed {
        return $this->input;
    }

    public function output(): mixed {
        return $this->output;
    }

    public function isComplete(): bool {
        return $this->isComplete;
    }

    public function validate() : ValidationResult {
        return $this->input->validate();
    }
}