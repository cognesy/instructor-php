<?php

use Cognesy\Polyglot\Embeddings\Drivers\Gemini\GeminiResponseAdapter;
use Cognesy\Polyglot\Embeddings\Drivers\Gemini\GeminiUsageFormat;

it('parses Gemini embeddings response', function () {
    $adapter = new GeminiResponseAdapter(new GeminiUsageFormat());
    $data = [
        'embeddings' => [ [ 'values' => [0.5, 0.25] ] ],
        'input_tokens' => 3,
        'output_tokens' => 0,
    ];

    $res = $adapter->fromResponse($data);
    expect($res->vectors())->toHaveCount(1);
    expect($res->vectors()[0]->values())->toBe([0.5, 0.25]);
    expect($res->usage()->input())->toBe(3);
});
