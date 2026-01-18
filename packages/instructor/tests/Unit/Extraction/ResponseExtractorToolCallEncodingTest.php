<?php declare(strict_types=1);

use Cognesy\Instructor\Extraction\ResponseExtractor;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

it('returns failure when tool call JSON encoding fails', function () {
    $invalidUtf8 = "\xC3\x28";
    $toolCalls = new ToolCalls(new ToolCall('test', ['bad' => $invalidUtf8]));
    $response = new InferenceResponse(toolCalls: $toolCalls, finishReason: 'stop');

    $extractor = new ResponseExtractor();
    $result = $extractor->extract($response, OutputMode::Tools);

    expect($result->isFailure())->toBeTrue();
    expect($result->exception())->toBeInstanceOf(\JsonException::class);
});
