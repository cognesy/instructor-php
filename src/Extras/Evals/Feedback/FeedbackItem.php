<?php

namespace Cognesy\Instructor\Extras\Evals\Feedback;

use Cognesy\Instructor\Extras\Evals\Enums\FeedbackType;
use Cognesy\Instructor\Extras\Evals\Observation;
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

    public function toObservation() : Observation {
        return Observation::make(
            type: 'feedback',
            key: $this->context,
            value: $this->feedback,
            metadata: [
                'category' => $this->category,
            ],
        );
    }
}
