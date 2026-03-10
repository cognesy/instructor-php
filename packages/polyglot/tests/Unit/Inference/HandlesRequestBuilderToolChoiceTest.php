<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\ResponseFormat;
use Cognesy\Polyglot\Inference\Data\ToolChoice;
use Cognesy\Polyglot\Inference\Traits\HandlesRequestBuilder;

it('accepts typed tool choice via HandlesRequestBuilder', function () {
    $handler = new class {
        use HandlesRequestBuilder;

        public function __construct() {
            $this->requestBuilder = new InferenceRequestBuilder();
        }

        public function requestBuilder(): InferenceRequestBuilder {
            return $this->requestBuilder;
        }
    };

    $handler = $handler->withToolChoice(ToolChoice::auto());
    $request = $handler->requestBuilder()->create();

    expect($request->toolChoice()->isAuto())->toBeTrue();
});

it('accepts typed response format and messages via HandlesRequestBuilder', function () {
    $handler = new class {
        use HandlesRequestBuilder;

        public function __construct() {
            $this->requestBuilder = new InferenceRequestBuilder();
        }

        public function requestBuilder(): InferenceRequestBuilder {
            return $this->requestBuilder;
        }
    };

    $cachedMessages = Messages::fromArray([['role' => 'system', 'content' => 'You are helpful']]);
    $responseFormat = ResponseFormat::jsonObject();

    $handler = $handler
        ->withResponseFormat($responseFormat)
        ->withCachedContext(messages: $cachedMessages, responseFormat: $responseFormat);

    $request = $handler->requestBuilder()->create();

    expect($request->responseFormat())->toBe($responseFormat)
        ->and($request->cachedContext())->not()->toBeNull()
        ->and($request->cachedContext()?->messages())->toEqual($cachedMessages)
        ->and($request->cachedContext()?->responseFormat())->toBe($responseFormat);
});
