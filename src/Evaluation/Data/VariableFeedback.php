<?php

namespace Cognesy\Instructor\Evaluation\Data;

class VariableFeedback
{
    public function __construct(
        public string $variableName,
        public string $feedback,
    ) {}
}