<?php

namespace Cognesy\Evals\Feedback;

use Cognesy\Evals\Enums\FeedbackType;
use Cognesy\Evals\Observation;
use Cognesy\Instructor\Features\Schema\Attributes\Description;

class FeedbackItem
{
    #[Description('The name of the parameter that the feedback is about.')]
    public string $context = '';
    #[Description('The feedback on the parameters correctness or the issues with its value.')]
    public string $feedback = '';
    #[Description('The category of the feedback.')]
    public FeedbackType $category;

    public function __construct(
        string       $context = '',
        string       $feedback = '',
        FeedbackType $category = FeedbackType::Other,
    ) {
        $this->context = $context;
        $this->feedback = $feedback;
        $this->category = $category;
    }

    public function toObservation(array $params) : Observation {
        $key = $params['key'];
        unset($params['key']);
        return Observation::make(
            type: 'feedback',
            key: $key,
            value: $this->feedback,
            metadata: array_merge([
                'category' => $this->category->value,
                'context' => $this->context,
            ], $params),
        );
    }
}
