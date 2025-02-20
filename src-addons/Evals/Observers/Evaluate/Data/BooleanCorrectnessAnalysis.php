<?php

namespace Cognesy\Addons\Evals\Observers\Evaluate\Data;

use Cognesy\Addons\Evals\Feedback\FeedbackItem;
use Cognesy\Instructor\Features\Schema\Attributes\Description;

#[Description("The result of correctness evaluation.")]
class BooleanCorrectnessAnalysis
{
    #[Description("Step by step assessment of the expected versus actual results.")]
    public string $assessment;
    #[Description("Decision if the actual result is correct.")]
    public bool $isCorrect;
    #[Description("If the result is incorrect - list of individual issues found in the actual result considering the expected values. Otherwise empty.")]
    /** @var FeedbackItem[] */
    public array $feedback;
}
