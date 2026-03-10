<?php declare(strict_types=1);

use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Messages\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Messages\ToolCall;
use Cognesy\Instructor\Enums\OutputMode;

it('returns failure when tool call JSON encoding fails', function () {
    $invalidUtf8 = "\xC3\x28";
    $toolCalls = new ToolCalls(new ToolCall('test', ['bad' => $invalidUtf8]));
    $response = new InferenceResponse(toolCalls: $toolCalls, finishReason: 'stop');

    expect(fn() => ExtractionInput::fromResponse($response, OutputMode::Tools))
        ->toThrow(ExtractionException::class);
});
