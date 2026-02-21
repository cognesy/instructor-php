<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceResponseId;

it('uses typed response id and serializes it to string', function () {
    $response = new InferenceResponse(content: 'ok', finishReason: 'stop');
    $array = $response->toArray();
    $copy = InferenceResponse::fromArray($array);

    expect($response->id)->toBeInstanceOf(InferenceResponseId::class)
        ->and($array['id'])->toBeString()
        ->and($array['id'])->toBe($response->id->toString())
        ->and($copy->id)->toBeInstanceOf(InferenceResponseId::class)
        ->and($copy->id->toString())->toBe($response->id->toString());
});
