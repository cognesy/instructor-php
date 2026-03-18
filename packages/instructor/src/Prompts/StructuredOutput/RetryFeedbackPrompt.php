<?php declare(strict_types=1);

namespace Cognesy\Instructor\Prompts\StructuredOutput;

final class RetryFeedbackPrompt extends AbstractStructuredOutputPrompt
{
    public string $templateFile = 'retry-feedback.md.twig';
}
