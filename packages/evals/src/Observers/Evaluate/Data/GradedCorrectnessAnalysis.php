<?php declare(strict_types=1);

namespace Cognesy\Evals\Observers\Evaluate\Data;

use Cognesy\Evals\Enums\CorrectnessGrade;
use Cognesy\Schema\Attributes\Description;

#[Description("The result of correctness evaluation.")]
class GradedCorrectnessAnalysis
{
    #[Description("Step by step assessment of the expected versus actual results.")]
    public string $assessment;
    #[Description("Graded correctness of the result.")]
    public CorrectnessGrade $correctness;
    #[Description("If the result is incorrect - list of individual issues found in the actual result considering the expected values. Otherwise empty.")]
    /** @var \Cognesy\Evals\Feedback\FeedbackItem[] */
    public array $feedback;
}
