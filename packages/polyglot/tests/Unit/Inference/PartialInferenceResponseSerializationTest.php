<?php

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponseId;

it('round-trips partial inference response including response_data', function () {
    $httpResponse = HttpResponse::sync(200, ['x-test' => '1'], '{"ok":true}');
    $partial = new PartialInferenceResponse(
        contentDelta: 'partial',
        responseData: $httpResponse,
    );

    $data = $partial->toArray();
    $copy = PartialInferenceResponse::fromArray($data);

    expect($data['response_data'])->toBeArray()
        ->and($partial->id)->toBeInstanceOf(PartialInferenceResponseId::class)
        ->and($data['id'])->toBeString()
        ->and($data['id'])->toBe($partial->id->toString())
        ->and($copy->id)->toBeInstanceOf(PartialInferenceResponseId::class)
        ->and($copy->id->toString())->toBe($partial->id->toString())
        ->and($copy->responseData?->toArray())->toBe($httpResponse->toArray());
});
