<?php

use Cognesy\Polyglot\Embeddings\Drivers\Cohere\CohereResponseAdapter;
use Cognesy\Polyglot\Embeddings\Drivers\Cohere\CohereUsageFormat;

it('parses Cohere embeddings response to vectors and usage', function () {
    $adapter = new CohereResponseAdapter(new CohereUsageFormat());
    $data = [
        'embeddings' => [
            'float' => [ [0.1, 0.2, 0.3] ]
        ],
        'meta' => [ 'billed_units' => [ 'input_tokens' => 7 ] ],
    ];

    $res = $adapter->fromResponse($data);
    expect($res->vectors())->toHaveCount(1);
    expect($res->vectors()[0]->values())->toBe([0.1, 0.2, 0.3]);
});

