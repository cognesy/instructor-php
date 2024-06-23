<?php
namespace Cognesy\Instructor\Evaluation\ResponseModels;

use Cognesy\Instructor\Schema\Attributes\Instructions;

#[Instructions('Respond in English')]
class PromptImprovement {
    #[Instructions('Analyze the feedback to identify ways to improve the prompt.')]
    public string $analysisOfFeedback;
    #[Instructions('Analyze the examples to identify ways to improve the prompt.')]
    public string $analysisOfExamples;
    #[Instructions('State specific goals for prompt improvement, considering feedback and examples.')]
    public string $goals;
    #[Instructions('New, improved prompt that will help achieve the goals.')]
    public string $improvedPrompt;
}