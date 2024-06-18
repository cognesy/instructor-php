<?php

namespace Cognesy\Instructor\Evaluation\ResponseModels;

use Cognesy\Instructor\Evaluation\Data\Feedback;
use Cognesy\Instructor\Schema\Attributes\Description;

#[Description("The result of correctness evaluation.")]
class BooleanCorrectnessAnalysis
{
    #[Description("Step by step assessment of the expected versus actual results.")]
    public string $assessment;
    #[Description("Decision if the result is correct.")]
    public bool $isCorrect;
    #[Description("If the result is incorrect - list of individual issues found in the actual result considering the expected values. Otherwise empty.")]
    public Feedback $feedback;
}
