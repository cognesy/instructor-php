<?php

use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Traits\HandlesRequestBuilder;

it('accepts array tool choice via HandlesRequestBuilder', function () {
    $handler = new class {
        use HandlesRequestBuilder;

        public function __construct() {
            $this->requestBuilder = new InferenceRequestBuilder();
        }

        public function requestBuilder(): InferenceRequestBuilder {
            return $this->requestBuilder;
        }
    };

    $handler->withToolChoice(['type' => 'auto']);
    $request = $handler->requestBuilder()->create();

    expect($request->toolChoice())->toBe(['type' => 'auto']);
});
