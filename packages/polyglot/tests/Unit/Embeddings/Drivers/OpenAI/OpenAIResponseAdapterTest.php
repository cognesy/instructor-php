<?php

use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIResponseAdapter;
use Cognesy\Polyglot\Embeddings\Drivers\OpenAI\OpenAIUsageFormat;

it('parses OpenAI embeddings response to vectors and usage', function () {
    $adapter = new OpenAIResponseAdapter(new OpenAIUsageFormat());
    $data = [
        'data' => [
            ['index' => 0, 'embedding' => [0.11, 0.22]],
            ['index' => 1, 'embedding' => [0.33, 0.44]],
        ],
        'usage' => [ 'prompt_tokens' => 5 ],
    ];

    $res = $adapter->fromResponse($data);
    expect($res->vectors())->toHaveCount(2);
    expect($res->vectors()[0]->values())->toBe([0.11, 0.22]);
    expect($res->usage()->input())->toBe(5);
});

