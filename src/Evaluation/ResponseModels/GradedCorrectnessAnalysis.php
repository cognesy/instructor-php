<?php
namespace Cognesy\Instructor\Evaluation\ResponseModels;

use Cognesy\Instructor\Evaluation\Data\Feedback;
use Cognesy\Instructor\Evaluation\Metrics\GradedCorrectness;
use Cognesy\Instructor\Schema\Attributes\Description;

#[Description("The result of correctness evaluation.")]
class GradedCorrectnessAnalysis
{
    #[Description("Step by step assessment of the expected versus actual results.")]
    public string $assessment;
    #[Description("Grade to which the result is correct.")]
    public GradedCorrectness $correctness;
    #[Description("If the result is incorrect - list of individual issues found in the actual result considering the expected values. Otherwise empty.")]
    public Feedback $feedback;
}
