<?php declare(strict_types=1);

use Cognesy\Instructor\Data\OutputFormat;
use Cognesy\Instructor\Data\StructuredOutputRequest;
use Cognesy\Messages\Messages;

it('accepts constructor named args for structured output request fields', function () {
    $request = new StructuredOutputRequest(
        messages: Messages::fromString('Extract data'),
        requestedSchema: ['type' => 'object'],
        system: 'You are strict.',
        prompt: 'Return only JSON.',
        examples: [['input' => 'A', 'output' => 'B']],
        model: 'gpt-4o-mini',
        options: ['temperature' => 0.1],
        outputFormat: OutputFormat::array(),
    );

    expect($request->messages()->toArray()[0]['content'])->toBe('Extract data');
    expect($request->requestedSchema())->toBe(['type' => 'object']);
    expect($request->system())->toBe('You are strict.');
    expect($request->prompt())->toBe('Return only JSON.');
    expect($request->model())->toBe('gpt-4o-mini');
    expect($request->options())->toBe(['temperature' => 0.1]);
    expect($request->outputFormat()?->isArray())->toBeTrue();
});
