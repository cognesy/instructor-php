<?php declare(strict_types=1);

namespace Cognesy\Instructor\Prompts\StructuredOutput;

use Cognesy\Xprompt\Prompt;

abstract class AbstractStructuredOutputPrompt extends Prompt
{
    public ?string $templateDir = __DIR__ . '/../../../resources/prompts/structured-output';
}
