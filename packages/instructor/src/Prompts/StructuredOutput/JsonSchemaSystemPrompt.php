<?php declare(strict_types=1);

namespace Cognesy\Instructor\Prompts\StructuredOutput;

final class JsonSchemaSystemPrompt extends AbstractStructuredOutputPrompt
{
    public string $templateFile = 'json-schema-system.md.twig';
}
