<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

trait HandlesFieldExamples
{
    private array $examples;

    public function examples(array $examples) : self {
        $this->examples = $examples;
        return $this;
    }
}