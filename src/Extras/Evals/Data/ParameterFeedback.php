<?php

namespace Cognesy\Instructor\Extras\Evals\Data;

use Cognesy\Instructor\Features\Schema\Attributes\Description;

class ParameterFeedback
{
    #[Description('The name of the parameter that the feedback is about.')]
    public string $parameterName = '';
    #[Description('The feedback on the parameters correctness or the issues with its value.')]
    public string $feedback = '';

    public function __construct(
        string $parameterName = '',
        string $feedback = '',
    ) {
        $this->parameterName = $parameterName;
        $this->feedback = $feedback;
    }
}
