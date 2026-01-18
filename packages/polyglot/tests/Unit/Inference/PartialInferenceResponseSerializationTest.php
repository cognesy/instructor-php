<?php

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

it('round-trips partial inference response including response_data', function () {
    $httpResponse = HttpResponse::sync(200, ['x-test' => '1'], '{"ok":true}');
    $partial = new PartialInferenceResponse(
        contentDelta: 'partial',
        responseData: $httpResponse,
    );

    $data = $partial->toArray();
    $copy = PartialInferenceResponse::fromArray($data);

    expect($data['response_data'])->toBeArray();
    expect($copy->responseData?->toArray())->toBe($httpResponse->toArray());
});
