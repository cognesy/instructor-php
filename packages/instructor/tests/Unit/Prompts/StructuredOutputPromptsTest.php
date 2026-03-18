<?php declare(strict_types=1);

use Cognesy\Instructor\Prompts\StructuredOutput\DeserializationRepairPrompt;
use Cognesy\Instructor\Prompts\StructuredOutput\JsonSchemaSystemPrompt;
use Cognesy\Instructor\Prompts\StructuredOutput\JsonSystemPrompt;
use Cognesy\Instructor\Prompts\StructuredOutput\MdJsonSystemPrompt;
use Cognesy\Instructor\Prompts\StructuredOutput\RetryFeedbackPrompt;
use Cognesy\Instructor\Prompts\StructuredOutput\ToolsSystemPrompt;

it('renders json system prompt with task and examples markdown', function () {
    $text = JsonSystemPrompt::with(
        system: 'You are a precise extraction assistant.',
        task: 'Extract person details from the input.',
        examples_markdown: "- Input: Jane, 31\n- Output: {\"name\":\"Jane\",\"age\":31}",
        json_schema: '{"type":"object","properties":{"name":{"type":"string"}}}',
    )->render();

    expect($text)->toContain('You are a precise extraction assistant.')
        ->and($text)->toContain('## Task')
        ->and($text)->toContain('Extract person details from the input.')
        ->and($text)->toContain('## Examples')
        ->and($text)->toContain('Jane, 31')
        ->and($text)->toContain('"type":"object"')
        ->and($text)->toContain('strict JSON object');
});

it('renders mdjson system prompt with fenced-code requirement', function () {
    $text = MdJsonSystemPrompt::with(
        system: 'You are a precise extraction assistant.',
        task: 'Extract person details from the input.',
        json_schema: '{"type":"object"}',
    )->render();

    expect($text)->toContain('"type":"object"')
        ->and($text)->toContain('fenced `json` code block');
});

it('renders tools system prompt with tool context', function () {
    $text = ToolsSystemPrompt::with(
        system: 'You are a precise extraction assistant.',
        task: 'Extract person details from the input.',
        examples_markdown: '- Input: Jane, 31',
        tool_name: 'extract_person',
        tool_description: 'Extract person data from text.',
    )->render();

    expect($text)->toContain('extract_person')
        ->and($text)->toContain('Extract person data from text.')
        ->and($text)->toContain('## Examples')
        ->and($text)->toContain('Call the tool');
});

it('renders json-schema system prompt with schema metadata', function () {
    $text = JsonSchemaSystemPrompt::with(
        system: 'You are a precise extraction assistant.',
        task: 'Extract person details from the input.',
        json_schema: '{"type":"object","title":"person"}',
        schema_name: 'person_schema',
        schema_description: 'Structured person payload.',
    )->render();

    expect($text)->toContain('person_schema')
        ->and($text)->toContain('Structured person payload.')
        ->and($text)->toContain('"title":"person"');
});

it('renders retry feedback prompt with errors', function () {
    $text = RetryFeedbackPrompt::with(
        errors: 'Missing field `name`; age must be an integer.',
    )->render();

    expect($text)->toContain('Validation Errors')
        ->and($text)->toContain('Missing field `name`; age must be an integer.');
});

it('renders deserialization repair prompt with invalid payload and schema context', function () {
    $text = DeserializationRepairPrompt::with(
        invalid_payload: '{"name":123}',
        error: 'Field `name` must be a string.',
        json_schema: '{"type":"object","properties":{"name":{"type":"string"}}}',
    )->render();

    expect($text)->toContain('## Invalid Payload')
        ->and($text)->toContain('{"name":123}')
        ->and($text)->toContain('Field `name` must be a string.')
        ->and($text)->toContain('"type":"string"');
});
