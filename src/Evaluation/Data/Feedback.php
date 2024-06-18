<?php

namespace Cognesy\Instructor\Evaluation\Data;

class Feedback
{
    public function __construct(
        /** @var VariableFeedback[] $items */
        public array $items
    ) {}
}