<?php declare(strict_types=1);

namespace Cognesy\Instructor\Prompts\StructuredOutput;

final class JsonSystemPrompt extends AbstractStructuredOutputPrompt
{
    public string $templateFile = 'json-system.md.twig';
}
