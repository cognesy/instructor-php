<?php

namespace Cognesy\Instructor\Extras\Evals\Data;

use Cognesy\Instructor\Extras\Evals\Enums\FeedbackCategory;
use Cognesy\Instructor\Features\Schema\Attributes\Description;

class FeedbackItem
{
    #[Description('The name of the parameter that the feedback is about.')]
    public string $context = '';
    #[Description('The feedback on the parameters correctness or the issues with its value.')]
    public string $feedback = '';
    #[Description('The category of the feedback.')]
    public FeedbackCategory $category;

    public function __construct(
        string $context = '',
        string $feedback = '',
        FeedbackCategory $category = FeedbackCategory::Other,
    ) {
        $this->context = $context;
        $this->feedback = $feedback;
        $this->category = $category;
    }
}
