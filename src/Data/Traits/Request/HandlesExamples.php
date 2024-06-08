<?php

namespace Cognesy\Instructor\Data\Traits\Request;

trait HandlesExamples
{
    private string $examplesPrompt = "Examples:\n<|examples|>";
    private array $examples;

    public function examples() : array {
        return $this->examples;
    }

    public function withExamples(array $examples) : self {
        $this->examples = $examples;
        return $this;
    }
}
