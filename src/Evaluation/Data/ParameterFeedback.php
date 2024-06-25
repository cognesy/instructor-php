<?php

namespace Cognesy\Instructor\Evaluation\Data;

class ParameterFeedback
{
    public function __construct(
        public string $parameterName,
        public string $feedback,
    ) {}
}
