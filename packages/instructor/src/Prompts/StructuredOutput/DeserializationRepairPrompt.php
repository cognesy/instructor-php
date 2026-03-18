<?php declare(strict_types=1);

namespace Cognesy\Instructor\Prompts\StructuredOutput;

final class DeserializationRepairPrompt extends AbstractStructuredOutputPrompt
{
    public string $templateFile = 'deserialization-repair.md.twig';
}
