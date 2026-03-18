<?php declare(strict_types=1);

namespace Cognesy\Instructor\Prompts\StructuredOutput;

final class ToolsSystemPrompt extends AbstractStructuredOutputPrompt
{
    public string $templateFile = 'tools-system.md.twig';
}
