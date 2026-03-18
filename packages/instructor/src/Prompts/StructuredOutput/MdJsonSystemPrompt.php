<?php declare(strict_types=1);

namespace Cognesy\Instructor\Prompts\StructuredOutput;

final class MdJsonSystemPrompt extends AbstractStructuredOutputPrompt
{
    public string $templateFile = 'mdjson-system.md.twig';
}
