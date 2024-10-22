<?php

namespace Cognesy\Instructor\Extras\Evals\Evaluators\Data;

use Cognesy\Instructor\Extras\Evals\Enums\CorrectnessGrade;
use Cognesy\Instructor\Extras\Evals\Feedback\FeedbackItem;
use Cognesy\Instructor\Features\Schema\Attributes\Description;

#[Description("The result of correctness evaluation.")]
class GradedCorrectnessAnalysis
{
    #[Description("Step by step assessment of the expected versus actual results.")]
    public string $assessment;
    #[Description("Graded correctness of the result.")]
    public CorrectnessGrade $correctness;
    #[Description("If the result is incorrect - list of individual issues found in the actual result considering the expected values. Otherwise empty.")]
    /** @var FeedbackItem[] */
    public array $feedback;
}
